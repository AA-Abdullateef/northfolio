<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'image'      => $this->imageUrl(),
            'body'       => $this->body,
            'published'  => $this->published,
            'topic'      => $this->whenLoaded('topic', fn () => [
                'id'   => $this->topic?->id,
                'name' => $this->topic?->name,
            ]),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}