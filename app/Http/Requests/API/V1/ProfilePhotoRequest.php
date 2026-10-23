<?php

namespace App\Http\Requests\API\V1;

class ProfilePhotoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'profile_photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png',
                'max:1024',  // ~1000KB
            ],
        ];
    }
}