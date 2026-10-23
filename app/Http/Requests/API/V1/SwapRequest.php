<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Validation\Rule;

class SwapRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'from_asset_id' => [
                'required',
                'uuid',
                Rule::exists('assets', 'id')->where('active', true),
            ],
            'to_asset_id'   => [
                'required',
                'uuid',
                Rule::exists('assets', 'id')->where('active', true),
                'different:from_asset_id',
            ],
            'amount'        => ['required', 'numeric', 'decimal:0,8', 'gt:0', 'max:9999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_asset_id.different' => 'Source and target asset must be different.',
        ];
    }
}
