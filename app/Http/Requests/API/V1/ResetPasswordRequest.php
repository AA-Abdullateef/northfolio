<?php

namespace App\Http\Requests\API\V1;

class ResetPasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email'       => ['required', 'email', 'exists:users,email'],
            'reset_token' => ['required', 'string'],
            'password'    => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}