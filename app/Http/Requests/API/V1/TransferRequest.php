<?php

namespace App\Http\Requests\API\V1;

class TransferRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'asset_id'     => ['required', 'uuid', 'exists:assets,id'],
            'amount'       => ['required', 'numeric', 'decimal:0,8', 'gt:0', 'max:9999999999'],
            'recipient_id' => ['required', 'uuid', 'exists:wallets,id'],
        ];
    }
}
