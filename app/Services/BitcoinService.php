<?php

namespace App\Services;

use App\Exceptions\BitcoinTransactionException;
use BitWasp\Bitcoin\Address\AddressCreator;
use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Exceptions\RandomBytesFailure;
use BitWasp\Bitcoin\Key\Factory\PrivateKeyFactory;
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
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class BitcoinService
{
    /**
     * @throws RandomBytesFailure
     * @throws Exception
     */
    public function createWallet(): array
    {
        $cfgNetwork = config('bitcoin.network', 'testnet');
        $network = $cfgNetwork === 'mainnet' ? NetworkFactory::bitcoin() : NetworkFactory::bitcoinTestnet();

        Bitcoin::setNetwork($network);

        $privateFactory = new PrivateKeyFactory();
        $privateKey = $privateFactory->generateCompressed(new Random);

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

        return [
            'network' => $cfgNetwork,
            'wif' => $wif,
            'private_hex' => method_exists($privateKey, 'getHex') ? $privateKey->getHex() : null,
            'public_hex' => $publicKeyHex,
            'compressed' => $compressed,
            'address_bech32' => $bech32,
            'address_legacy' => $legacy,
        ];
    }

    /**
     * @throws ConnectionException
     */
    public function getBalance(string $address): array
    {
        $baseUrl = config('bitcoin.explorer.url');

        $response = Http::get("$baseUrl/address/$address");

        $data = $response->json();

        $confirmed =
            ($data['chain_stats']['funded_txo_sum'] ?? 0)
            - ($data['chain_stats']['spent_txo_sum'] ?? 0);

        $unconfirmed =
            ($data['mempool_stats']['funded_txo_sum'] ?? 0)
            - ($data['mempool_stats']['spent_txo_sum'] ?? 0);

        $total = $confirmed + $unconfirmed;

        return [
            'address' => $address,

            'confirmed_sats' => $confirmed,
            'unconfirmed_sats' => $unconfirmed,
            'total_sats' => $total,

            'confirmed_btc' => $confirmed / 100_000_000,
            'unconfirmed_btc' => $unconfirmed / 100_000_000,
            'total_btc' => $total / 100_000_000,
        ];
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function sendTransaction(array $data): array
    {
        $from = $data['from_address'];
        $to = $data['to_address'];
        $amount = (int)$data['amount_sats'];

        $cfgNetwork = config('bitcoin.network', 'testnet');
        $network = $cfgNetwork === 'mainnet' ? NetworkFactory::bitcoin() : NetworkFactory::bitcoinTestnet();
        Bitcoin::setNetwork($network);

        $baseUrl = config('bitcoin.explorer.url');
        $utxoResponse = Http::get("$baseUrl/address/$from/utxo");

        if (!$utxoResponse->successful()) {
            throw new BitcoinTransactionException(
                'Failed to fetch UTXOs',
                Response::HTTP_BAD_GATEWAY,
                [
                    'status' => $utxoResponse->status(),
                    'address' => $from,
                ]
            );
        }

        $utxos = $utxoResponse->json();

        if (empty($utxos)) {
            throw new BitcoinTransactionException(
                'No UTXOs found for address',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                [
                    'address' => $from
                ]
            );
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
            throw new BitcoinTransactionException(
                'Insufficient funds',
                Response::HTTP_BAD_REQUEST,
                [
                    'address' => $from,
                    'need_sats' => $need,
                    'available_sats' => $totalAvailable,
                ]
            );
        }

        $prevTxOuts = [];

        foreach ($utxos as $i => $u) {
            $txid = $u['txid'];
            $vout = (int)$u['vout'];

            $txResp = Http::get("$baseUrl/tx/$txid");
            if (!$txResp->successful()) {
                throw new BitcoinTransactionException(
                    'Failed to fetch tx data',
                    Response::HTTP_BAD_GATEWAY,
                    [
                        'address' => $from,
                        'txid' => $txid,
                    ]
                );
            }

            $txData = $txResp->json();

            if (!isset($txData['vout'][$vout])) {
                throw new BitcoinTransactionException(
                    'Vout not found in tx',
                    Response::HTTP_BAD_REQUEST,
                    [
                        'address' => $from,
                        'txid' => $txid,
                        'vout' => $vout,
                    ]
                );
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
        $privateFactory = new PrivateKeyFactory($ecAdapter);
        $privateKey = $privateFactory->fromWif($data['wif'], $network);

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
            throw new BitcoinTransactionException(
                'Broadcast failed',
                Response::HTTP_BAD_REQUEST,
                [
                    'address' => $from,
                    'status' => $broadcastResponse->status(),
                    'body' => $broadcastResponse->body(),
                ]
            );
        }

        $txidFromApi = trim($broadcastResponse->body());

        return [
            'success' => true,
            'txid' => $txidFromApi,
            'raw_tx_hex' => $rawHex,
            'explorer_url' => "https://blockstream.info/testnet/tx/{$txidFromApi}",
            'change_address' => $changeAddress,
            'change_sats' => $change,
            'fee_sats' => $fee,
        ];
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function estimateFee(array $data): array
    {
        $from = $data['from_address'];

        $cfgNetwork = config('bitcoin.network', 'testnet');
        $network = $cfgNetwork === 'mainnet' ? NetworkFactory::bitcoin() : NetworkFactory::bitcoinTestnet();
        Bitcoin::setNetwork($network);

        $baseUrl = config('bitcoin.explorer.url');
        $utxoResponse = Http::get("$baseUrl/address/$from/utxo");
        if (!$utxoResponse->successful()) {
            throw new BitcoinTransactionException(
                'Failed to fetch UTXOs',
                Response::HTTP_BAD_GATEWAY,
                [
                    'status' => $utxoResponse->status(),
                ]
            );
        }
        $utxos = $utxoResponse->json();
        if (empty($utxos)) {
            throw new BitcoinTransactionException(
                'No UTXOs found for address',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $feesResp = Http::get("$baseUrl/fee-estimates");
        if (!$feesResp->successful()) {
            throw new BitcoinTransactionException(
                'Failed to fetch fee rates',
                Response::HTTP_BAD_GATEWAY,
            );
        }
        $feeRates = $feesResp->json();

        $satPerByte = ceil($feeRates['3'] ?? 10);
        $inputsCount = count($utxos);
        $outputsCount = 2;
        $estimatedSize = $inputsCount * 68 + $outputsCount * 31 + 10;
        $fee = $estimatedSize * $satPerByte;

        return [
            'inputs_count' => $inputsCount,
            'outputs_count' => $outputsCount,
            'estimated_size_bytes' => $estimatedSize,
            'sat_per_byte' => $satPerByte,
            'fee_sats' => $fee,
            'fee_btc' => $fee / 100_000_000,
        ];
    }

    /**
     * @throws ConnectionException
     */
    public function checkTransaction(string $tx): array
    {
        $baseUrl = config('bitcoin.explorer.url');
        $response = Http::get("$baseUrl/tx/$tx");

        if (!$response->successful()) {
            throw new BitcoinTransactionException(
                'Failed to fetch tx info',
                Response::HTTP_BAD_GATEWAY,
            );
        }

        $txData = $response->json();

        return [
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
        ];
    }

    /**
     * @throws ConnectionException
     */
    public function getAddressTransactions(string $address): array
    {
        $baseUrl = config('bitcoin.explorer.url');

        $allTxs = [];
        $lastSeenTxId = null;

        while (true) {
            $url = $lastSeenTxId === null
                ? "$baseUrl/address/$address/txs"
                : "$baseUrl/address/$address/txs/chain/$lastSeenTxId";

            $response = Http::get($url);

            if (!$response->successful()) {
                throw new BitcoinTransactionException(
                    'Failed to fetch transactions',
                    Response::HTTP_BAD_GATEWAY,
                    [
                        'address' => $address,
                    ]
                );
            }

            $chunk = $response->json();

            if (!is_array($chunk) || empty($chunk)) {
                break;
            }

            $allTxs = array_merge($allTxs, $chunk);

            if (count($chunk) < 25) {
                break;
            }

            $lastTx = end($chunk);
            $lastSeenTxId = $lastTx['txid'] ?? null;

            if (!$lastSeenTxId) {
                break;
            }
        }

        return [
            'address' => $address,
            'transactions_count' => count($allTxs),
            'transactions' => $allTxs,
        ];
    }

}
