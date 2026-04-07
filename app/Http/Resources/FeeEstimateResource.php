<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeEstimateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'inputs_count' => $this->inputsCount,
            'outputs_count' => $this->outputsCount,
            'estimated_size_bytes' => $this->estimatedSizeBytes,
            'sat_per_byte' => $this->satPerByte,
            'fee_sats' => $this->feeSats,
            'fee_btc' => $this->feeBtc,
        ];
    }
}

