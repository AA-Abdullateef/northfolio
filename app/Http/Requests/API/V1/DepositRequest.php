<?php

namespace App\Http\Requests\API\V1;

class DepositRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'usd_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999.99'],
        ];
    }
}
