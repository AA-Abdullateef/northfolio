<?php

namespace App\Http\Requests\API\V1;

class WalletImportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'mnemonic' => ['required', 'string'],
        ];
    }
}