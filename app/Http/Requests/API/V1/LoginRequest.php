<?php

namespace App\Http\Requests\API\V1;

class LoginRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'user'     => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}