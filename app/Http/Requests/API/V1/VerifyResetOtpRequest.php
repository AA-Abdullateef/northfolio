<?php

namespace App\Http\Requests\API\V1;

class VerifyResetOtpRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'otp'   => ['required', 'string', 'digits:6'],
        ];
    }
}