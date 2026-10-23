<?php

namespace App\Http\Requests\API\V1;

class RegisterRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'username'    => ['required', 'string', 'max:30', 'unique:users,username'],
            'email'       => ['required', 'email', 'max:191', 'unique:users,email'],
            'country_id'  => ['required', 'uuid', 'exists:countries,id'],
            'password'    => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}