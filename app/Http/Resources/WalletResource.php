<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'network' => $this->network,
            'wif' => $this->wif,
            'private_hex' => $this->privateHex,
            'public_hex' => $this->publicKeyHex,
            'compressed' => $this->compressed,
            'address_bech32' => $this->bech32,
            'address_legacy' => $this->legacy,
        ];
    }
}
