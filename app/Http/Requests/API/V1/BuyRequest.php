<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Validation\Rule;

class BuyRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'asset_id'   => ['required', 'uuid', Rule::exists('assets', 'id')->where('active', true)],
            'usd_amount' => ['required', 'numeric', 'decimal:0,8', 'gt:0', 'max:9999999999'],
        ];
    }
}
