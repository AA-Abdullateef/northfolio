<?php

namespace App\Http\Requests\API\V1;

use App\Models\Method;
use Illuminate\Validation\Rule;

class WithdrawRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'asset_id'  => ['required', 'uuid', 'exists:assets,id'],
            'sub_method_id' => [
                'required',
                'uuid',
                Rule::exists('sub_methods', 'id')->where(
                    fn ($query) => $query->whereIn('method_id', Method::crypto()->pluck('id'))
                ),
            ],
            'reference' => ['required', 'string', 'min:9'],  // external wallet address
            'amount'    => ['required', 'numeric', 'decimal:0,8', 'gt:0', 'max:9999999999'],
        ];
    }
}
