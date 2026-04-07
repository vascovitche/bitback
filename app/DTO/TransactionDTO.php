<?php

namespace App\DTO;

class TransactionDTO
{
    public function __construct(
        public ?string $txid,
        public bool $confirmed,
        public ?int $blockHeight,
        public ?string $blockTime,
        public ?int $fee,
        public ?int $size,
        public ?int $vinCount,
        public ?int $voutCount,
    )
    {
    }
}

