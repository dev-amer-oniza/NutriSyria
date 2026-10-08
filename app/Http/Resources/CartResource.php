<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // ── Item snapshot (what user saw at add-time) ──────────
            'item_id' => $this->item_id,
            'item_name' => $this->item_name,
            'description' => $this->description,

            // ── Pricing ───────────────────────────────────────────
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'line_total' => $this->line_total,   // from getLineTotalAttribute()

        ];
    }
}
