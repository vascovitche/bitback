<?php

namespace App\DTO;

class SendTransactionDTO
{
    public function __construct(
        public string  $fromAddress,
        public string  $privateHex,
        public string  $toAddress,
        public int     $amountSats,
        public int     $feeSats,
        public ?string $changeAddress = null,
    )
    {
    }

    public static function fromRequest(array $validated): self
    {
        return new self(
            fromAddress: $validated['from_address'],
            privateHex: $validated['private_hex'],
            toAddress: $validated['to_address'],
            amountSats: (int)$validated['amount_sats'],
            feeSats: (int)$validated['fee_sats'],
            changeAddress: $validated['change_address'] ?? null,
        );
    }
}
