<?php

namespace Feature;

use Tests\TestCase;

class BitcoinTest extends TestCase
{

    private string $firstAddress = 'tb1...';
    private string $wif = 'cSbyhfne...';
    private string $secondAddress = 'tb1...';
    private int $amountSats = 1000;
    private int $feeSats = 1000;
    private string $txId = '93dd...';

    public function test_bitcoin_create_wallet(): void
    {
        $response = $this->getJson('api/bitcoin/wallet')->dump();
        $response->assertStatus(200);
    }

    public function test_bitcoin_address_balance(): void
    {
        $response = $this->getJson("api/bitcoin/balance/{$this->firstAddress}")->dump();
        $response->assertStatus(200);
    }

    public function test_tx(): void
    {
        $response = $this->postJson('api/bitcoin/tx', [
            'from_address' => $this->firstAddress,
            'wif' => $this->wif,
            'to_address' => $this->secondAddress,
            'amount_sats' => $this->amountSats,
            'fee_sats' => $this->feeSats,
        ])->dump();
        $response->assertStatus(200);
    }

    public function test_tx_check(): void
    {
        $response = $this->getJson("api/bitcoin/tx/{$this->txId}")->dump();
        $response->assertStatus(200);
    }

}
