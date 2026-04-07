<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressTransactionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'address' => $this->address,
            'transactions_count' => $this->transactionsCount,
            'transactions' => $this->transactions,
        ];
    }
}

