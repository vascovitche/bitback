<?php

namespace App\DTO;

class EstimateFeeDTO
{
    public function __construct(
        public string $fromAddress,
        public string $toAddress,
        public int    $amountSats,
    )
    {
    }

    public static function fromRequest(array $validated): self
    {
        return new self(
            fromAddress: $validated['from_address'],
            toAddress: $validated['to_address'],
            amountSats: (int)$validated['amount_sats'],
        );
    }
}

