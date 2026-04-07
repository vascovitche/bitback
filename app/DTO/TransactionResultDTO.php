<?php

namespace App\DTO;

class TransactionResultDTO
{
    public function __construct(
        public bool $success,
        public string $txid,
        public string $rawTxHex,
        public string $explorerUrl,
        public string $changeAddress,
        public int $changeSats,
        public int $feeSats,
    )
    {
    }
}

