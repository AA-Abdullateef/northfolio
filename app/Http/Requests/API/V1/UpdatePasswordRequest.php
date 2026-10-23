<?php

namespace App\Http\Requests\API\V1;

class UpdatePasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'old_password' => ['required', 'string'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}