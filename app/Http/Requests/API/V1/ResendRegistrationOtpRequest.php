<?php

namespace App\Http\Requests\API\V1;

class ResendRegistrationOtpRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
        ];
    }
}