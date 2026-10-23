<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,

            'type'           => $this->type,
            'type_label'     => $this->typeLabel(),

            // Exact decimal string with 8 places (never a float).
            'amount'         => (string) $this->amount,

            'asset_id'       => $this->asset_id,
            'asset_symbol'   => $this->asset?->name,
            'asset'          => new AssetResource(
                $this->whenLoaded('asset')
            ),

            'sub_method_id'  => $this->sub_method_id,
            'sub_method'     => new SubMethodResource(
                $this->whenLoaded('subMethod')
            ),

            'status'         => $this->status,
            'status_label'   => $this->statusLabel(),

            'reference'      => $this->reference,
            'meta'           => $this->meta,

            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
