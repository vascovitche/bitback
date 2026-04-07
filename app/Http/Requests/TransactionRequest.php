<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'from_address' => 'required|string',
            'private_hex' => 'required|string|size:64',
            'to_address' => 'required|string',
            'amount_sats' => 'required|integer|min:1',
            'fee_sats' => 'required|integer|min:1',
            'change_address' => 'sometimes|string',
        ];
    }
}
