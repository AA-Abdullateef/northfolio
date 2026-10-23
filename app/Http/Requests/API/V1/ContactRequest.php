<?php

namespace App\Http\Requests\API\V1;

class ContactRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255'],
            'phone'   => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}