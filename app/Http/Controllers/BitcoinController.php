<?php

namespace App\Http\Controllers;

use App\Exceptions\BitcoinTransactionException;
use App\Http\Requests\FeeRequest;
use App\Http\Requests\TransactionRequest;
use App\Http\Resources\AddressTransactionsResource;
use App\Http\Resources\BalanceResource;
use App\Http\Resources\FeeEstimateResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\TransactionResultResource;
use App\Http\Resources\WalletResource;
use App\DTO\EstimateFeeDTO;
use App\DTO\SendTransactionDTO;
use App\Services\BitcoinService;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class BitcoinController extends Controller
{

    public function __construct(private readonly BitcoinService $service)
    {
    }

    public function wallet()
    {
        try {
            $wallet = $this->service->createWallet();
            return response()->json(WalletResource::make($wallet));
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to create wallet',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function balance(string $address)
    {
        try {
            $balance = $this->service->getBalance($address);
            return response()->json(BalanceResource::make($balance));
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch address balance info',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function tx(TransactionRequest $request)
    {
        try {
            $data = SendTransactionDTO::fromRequest($request->validated());
            $result = $this->service->sendTransaction($data);
            return response()->json(TransactionResultResource::make($result));
        } catch (BitcoinTransactionException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ], $e->getStatus());
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to send transaction',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function txCheck(string $tx)
    {
        try {
            $transaction = $this->service->checkTransaction($tx);
            return response()->json(TransactionResource::make($transaction));
        } catch (BitcoinTransactionException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ], $e->getStatus());
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to check transaction',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function fee(FeeRequest $request)
    {
        try {
            $data = EstimateFeeDTO::fromRequest($request->validated());
            $estimate = $this->service->estimateFee($data);
            return response()->json(FeeEstimateResource::make($estimate));
        } catch (BitcoinTransactionException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ], $e->getStatus());
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to estimate fee',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function txs(string $address)
    {
        try {
            $addressTransactions = $this->service->getAddressTransactions($address);
            return response()->json(AddressTransactionsResource::make($addressTransactions));
        } catch (BitcoinTransactionException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ], $e->getStatus());
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to get address transactions',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
