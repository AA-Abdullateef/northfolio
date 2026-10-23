<?php

namespace App\Http\Requests\API\V1;

use App\Models\Method;
use Illuminate\Validation\Rule;

class SellRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'asset_id'      => [
                'required',
                'uuid',
                Rule::exists('assets', 'id')->where('active', true),
            ],
            'amount'        => ['required', 'numeric', 'decimal:0,8', 'gt:0', 'max:9999999999'],
            'sub_method_id' => [
                'nullable',
                'uuid',
                Rule::exists('sub_methods', 'id')->where(
                    fn ($query) => $query->whereIn('method_id', Method::fiat()->pluck('id'))
                ),
            ],
            'reference'     => ['nullable', 'string', 'max:191'],
        ];
    }
}
