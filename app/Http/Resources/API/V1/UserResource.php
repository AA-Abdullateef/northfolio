<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'username'          => $this->username,
            'email'             => $this->email,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'profile_completed' => (bool) $this->profile_completed,
            'country_id'        => $this->country_id,
            'two_factor'        => $this->two_factor,
            'status'            => $this->status,
            'admin'             => (bool) $this->admin,
            'created_at'        => $this->created_at->toISOString(),
            'profile'           => $this->whenLoaded('profile', fn () => new ProfileResource($this->profile)),
            'photo'             => $this->whenLoaded('photo',   fn () => new ProfilePhotoResource($this->photo)),
        ];
    }
}