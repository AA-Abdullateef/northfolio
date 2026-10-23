<?php

namespace App\Http\Requests\API\V1;

class UpdateProfileRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email'      => ['required', 'email', 'max:191', 'unique:users,email,' . $this->user()->id],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'first_name' => ['nullable', 'string', 'max:55'],
            'last_name'  => ['nullable', 'string', 'max:55'],
            'gender'     => ['nullable', 'string', 'in:Male,Female,Other'],
            'phone'      => ['nullable', 'string', 'max:25'],
            'state'      => ['nullable', 'string', 'max:50'],
            'address'    => ['nullable', 'string', 'max:100'],
            'zip'        => ['nullable', 'string', 'max:25'],
            'dob'        => ['nullable', 'date', 'before:today'],
        ];
    }
}