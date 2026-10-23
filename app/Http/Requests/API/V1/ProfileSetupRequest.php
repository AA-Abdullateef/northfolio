<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Validation\Rule;
use App\Models\User;

class ProfileSetupRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $user = $this->user();
        $userId = $user instanceof User ? $user->id : null;

        return [
            'username'   => ['required', 'string', 'max:30', Rule::unique('users', 'username')->ignore($userId)],
            'email'      => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($userId)],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'phone'      => ['nullable', 'string', 'max:25'],
        ];
    }
}
