<?php

namespace App\Http\Resources\API\V1;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'label'            => $this->label,
            'icon'             => $this->icon ? asset('assets/icons/' . $this->icon) : null,
            'decimals'         => (int) ($this->decimals ?? 8),
            // Exact decimal string (8 dp); null when unpriced.
            'price_usd'        => $this->current_price !== null ? (string) $this->current_price : null,
            'price_updated_at' => $this->price_updated_at?->toISOString(),
            // Charge rates — null means not configured (treat as 0% client-side).
            'swap_charge_rate' => $this->swap_charge_rate !== null ? Money::of($this->swap_charge_rate) : null,
            'buy_charge_rate'  => $this->buy_charge_rate  !== null ? Money::of($this->buy_charge_rate) : null,
            'sell_charge_rate' => $this->sell_charge_rate !== null ? Money::of($this->sell_charge_rate) : null,
        ];
    }
}
