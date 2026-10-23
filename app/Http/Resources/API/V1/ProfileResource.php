<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'gender'    => $this->gender,
            'phone'     => $this->phone,
            'state'     => $this->state,
            'address'   => $this->address,
            'zip'       => $this->zip,
            'dob'       => $this->dob?->toDateString(),
        ];
    }
}