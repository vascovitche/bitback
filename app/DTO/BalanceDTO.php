<?php

namespace App\DTO;

class BalanceDTO
{
    public function __construct(
        public string $address,
        public int $confirmedSats,
        public int $unconfirmedSats,
        public int $totalSats,
        public float $confirmedBtc,
        public float $unconfirmedBtc,
        public float $totalBtc,
    )
    {
    }
}

