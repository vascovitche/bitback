<?php

namespace App\Http\Controllers;

use BitWasp\Bitcoin\Address\AddressCreator;
use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Key\PrivateKeyFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Script\WitnessProgram;
use BitWasp\Bitcoin\Transaction\Factory\Signer;
use BitWasp\Bitcoin\Transaction\Factory\TxBuilder;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Buffertools\Buffer;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BitcoinController extends Controller
{
    /**
     * @throws Exception
     */
    public function wallet()
    {
        $cfgNetwork = config('bitcoin.network', 'testnet');
        $network = $cfgNetwork === 'mainnet' ? NetworkFactory::bitcoin() : NetworkFactory::bitcoinTestnet();

        Bitcoin::setNetwork($network);

        try {
            $privateFactory = new PrivateKeyFactory();
            $privateKey = $privateFactory->create(true);

            $publicKey = $privateKey->getPublicKey();
            $compressed = $publicKey->isCompressed();

            $publicKeyHex = $publicKey->getHex();

            $pubKeyHash = $publicKey->getPubKeyHash();

            $p2wpkhWP = WitnessProgram::v0($pubKeyHash);
            $segwit = new SegwitAddress($p2wpkhWP);
            $bech32 = $segwit->getAddress($network);

            $p2pkh = new PayToPubKeyHashAddress($pubKeyHash);
            $legacy = $p2pkh->getAddress($network);

            $wif = method_exists($privateKey, 'toWif') ? $privateKey->toWif() : null;

            return response()->json([
                'network' => $cfgNetwork,
                'wif' => $wif,
                'private_hex' => method_exists($privateKey, 'getHex') ? $privateKey->getHex() : null,
                'public_hex' => $publicKeyHex,
                'compressed' => $compressed,
                'address_bech32' => $bech32,
                'address_legacy' => $legacy,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to create wallet',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @throws ConnectionException
     */
    public function balance(string $address)
    {

        $baseUrl = config('bitcoin.explorer.url');

        $response = Http::get("$baseUrl/address/$address");

        if (!$response->successful()) {
            return response()->json([
                'error' => 'Failed to fetch address info',
                'status' => $response->status(),
            ], 500);
        }

        $data = $response->json();

        $confirmed =
            ($data['chain_stats']['funded_txo_sum'] ?? 0)
            - ($data['chain_stats']['spent_txo_sum'] ?? 0);

        $unconfirmed =
            ($data['mempool_stats']['funded_txo_sum'] ?? 0)
            - ($data['mempool_stats']['spent_txo_sum'] ?? 0);

        $total = $confirmed + $unconfirmed;

        return response()->json([
            'address' => $address,

            'confirmed_sats' => $confirmed,
            'unconfirmed_sats' => $unconfirmed,
            'total_sats' => $total,

            'confirmed_btc' => $confirmed / 100_000_000,
            'unconfirmed_btc' => $unconfirmed / 100_000_000,
            'total_btc' => $total / 100_000_000,
        ]);
    }

    public function tx(Request $request)
    {
        $data = $request->validate([
            'from_address' => 'required|string',
            'wif' => 'required|string',
            'to_address' => 'required|string',
            'amount_sats' => 'required|integer|min:546',
            'fee_sats' => 'required|integer|min:546',
            'change_address' => 'sometimes|string',
        ]);

        $from = $data['from_address'];
        $to = $data['to_address'];
        $amount = (int)$data['amount_sats'];

        $cfgNetwork = config('bitcoin.network', 'testnet');
        $network = $cfgNetwork === 'mainnet' ? NetworkFactory::bitcoin() : NetworkFactory::bitcoinTestnet();
        Bitcoin::setNetwork($network);

        $baseUrl = config('bitcoin.explorer.url');
        $utxoResponse = Http::get("$baseUrl/address/$from/utxo");
        if (!$utxoResponse->successful()) {
            return response()->json(['error' => 'Failed to fetch UTXOs', 'status' => $utxoResponse->status()], 500);
        }
        $utxos = $utxoResponse->json();
        if (empty($utxos)) {
            return response()->json(['error' => 'No UTXOs found for address'], 422);
        }

        $inputsCount = count($utxos);
        $outputsCount = 2;

        $feesResp = Http::get("$baseUrl/fee-estimates");
        $feeRates = $feesResp->json();

        $satPerByte = ceil($feeRates['3'] ?? 10);

        $estimatedSize = $inputsCount * 68 + $outputsCount * 31 + 10;

        $fee = $estimatedSize * $satPerByte;

        $need = $amount + $fee;

        $totalAvailable = array_sum(
            array_map(
                fn($u) => (int)($u['value'] ?? 0),
                $utxos
            )
        );

        if ($totalAvailable < $need) {
            return response()->json([
                'error' => 'Insufficient funds',
                'need_sats' => $need,
                'available_sats' => $totalAvailable,
            ], 400);
        }

        $prevTxOuts = [];

        foreach ($utxos as $i => $u) {
            $txid = $u['txid'];
            $vout = (int)$u['vout'];

            $txResp = Http::get("$baseUrl/tx/$txid");
            if (!$txResp->successful()) {
                return response()->json([
                    'error' => 'Failed to fetch tx data',
                    'txid' => $txid,
                ], 500);
            }

            $txData = $txResp->json();

            if (!isset($txData['vout'][$vout])) {
                return response()->json([
                    'error' => 'vout not found in tx',
                    'txid' => $txid,
                    'vout' => $vout,
                ], 422);
            }

            $prev = $txData['vout'][$vout];

            $scriptPubKeyHex = $prev['scriptpubkey'];
            $value = (int)$prev['value'];

            $prevTxOuts[$i] = new TransactionOutput(
                $value,
                ScriptFactory::fromHex($scriptPubKeyHex)
            );
        }

        $totalIn = 0;
        foreach ($utxos as $u) {
            $totalIn += (int)$u['value'];
        }

        $change = $totalIn - $need;

        $changeAddress = $data['change_address'] ?? $data['from_address'];

        $addressCreator = new AddressCreator();
        $toAddressObj = $addressCreator->fromString($to);
        $changeAddressObj = $addressCreator->fromString($changeAddress);

        $builder = new TxBuilder();

        foreach ($utxos as $utxo) {
            $builder->spendOutPoint(
                new OutPoint(
                    Buffer::hex($utxo['txid'], 32),
                    (int)$utxo['vout']
                )
            );
        }

        $builder->payToAddress($amount, $toAddressObj);

        if ($change > 0) {
            if ($change >= 546) {
                $builder->payToAddress($change, $changeAddressObj);
            } else {
                $fee += $change;
                $change = 0;
            }
        }

        $unsignedTx = $builder->get();

        $ecAdapter = Bitcoin::getEcAdapter();
        $privateKey = PrivateKeyFactory::fromWif($data['wif'], $ecAdapter);

        $signer = new Signer($unsignedTx, $ecAdapter);

        foreach ($prevTxOuts as $i => $prevTxOut) {
            $input = $signer->input($i, $prevTxOut);
            $input->sign($privateKey);
        }

        $signedTx = $signer->get();

        $rawHex = $signedTx->getHex();

        $broadcastResponse = Http::withHeaders([
            'Content-Type' => 'text/plain',
        ])->withBody($rawHex, 'text/plain')
            ->post("$baseUrl/tx");

        if (!$broadcastResponse->successful()) {
            return response()->json([
                'error' => 'Broadcast failed',
                'status' => $broadcastResponse->status(),
                'body' => $broadcastResponse->body(),
            ], 422);
        }

        $txidFromApi = trim($broadcastResponse->body());

        return response()->json([
            'success' => true,
            'txid' => $txidFromApi,
            'raw_tx_hex' => $rawHex,
            'explorer_url' => "https://blockstream.info/testnet/tx/{$txidFromApi}",
            'change_address' => $changeAddress,
            'change_sats' => $change,
            'fee_sats' => $fee,
        ]);
    }

    public function txCheck(string $tx)
    {
        $baseUrl = config('bitcoin.explorer.url');
        $response = Http::get("$baseUrl/tx/$tx");

        if (!$response->successful()) {
            return response()->json([
                'error' => 'Failed to fetch tx info',
                'status' => $response->status(),
            ], 500);
        }

        $txData = $response->json();

        return response()->json([
            'txid' => $txData['txid'] ?? null,
            'confirmed' => $txData['status']['confirmed'] ?? false,
            'block_height' => $txData['status']['block_height'] ?? null,
            'block_time' => isset($txData['status']['block_time'])
                ? date('c', $txData['status']['block_time'])
                : null,
            'fee' => $txData['fee'] ?? null,
            'size' => $txData['size'] ?? null,
            'vin_count' => isset($txData['vin']) ? count($txData['vin']) : null,
            'vout_count' => isset($txData['vout']) ? count($txData['vout']) : null,
        ]);
    }

}
