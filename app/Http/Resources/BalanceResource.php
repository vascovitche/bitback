<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'address' => $this->address,
            'confirmed_sats' => $this->confirmedSats,
            'unconfirmed_sats' => $this->unconfirmedSats,
            'total_sats' => $this->totalSats,
            'confirmed_btc' => $this->confirmedBtc,
            'unconfirmed_btc' => $this->unconfirmedBtc,
            'total_btc' => $this->totalBtc,
        ];
    }
}

