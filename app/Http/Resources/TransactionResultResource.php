<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => $this->success,
            'txid' => $this->txid,
            'raw_tx_hex' => $this->rawTxHex,
            'explorer_url' => $this->explorerUrl,
            'change_address' => $this->changeAddress,
            'change_sats' => $this->changeSats,
            'fee_sats' => $this->feeSats,
        ];
    }
}

