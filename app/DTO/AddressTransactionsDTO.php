<?php

namespace App\DTO;

class AddressTransactionsDTO
{
    public function __construct(
        public string $address,
        public int $transactionsCount,
        public array $transactions,
    )
    {
    }
}

