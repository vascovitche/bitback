<?php

namespace App\Http\Controllers;

use App\Exceptions\BitcoinTransactionException;
use App\Http\Requests\FeeRequest;
use App\Http\Requests\TransactionRequest;
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
            return response()->json($this->service->createWallet());
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
            return response()->json($this->service->getBalance($address));
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
            return response()->json($this->service->sendTransaction($request->validated()));
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
            return response()->json($this->service->checkTransaction($tx));
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
            return response()->json($this->service->estimateFee($request->validated()));
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
            return response()->json($this->service->getAddressTransactions($address));
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
