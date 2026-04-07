<?php

namespace App\DTO;

class WalletDTO
{
    public function __construct(
        public string $network,
        public string $wif,
        public ?string $privateHex,
        public string $publicKeyHex,
        public bool $compressed,
        public string $bech32,
        public string $legacy,
    )
    {
    }
}
