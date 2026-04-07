<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'txid' => $this->txid,
            'confirmed' => $this->confirmed,
            'block_height' => $this->blockHeight,
            'block_time' => $this->blockTime,
            'fee' => $this->fee,
            'size' => $this->size,
            'vin_count' => $this->vinCount,
            'vout_count' => $this->voutCount,
        ];
    }
}

