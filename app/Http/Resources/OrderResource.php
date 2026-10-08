<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       // $restaurant = $this->tenant->restaurant;
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'payment_method'  => $this->payment_method,
            'restaurant_name' => $this->restaurant_name,
            'tenant_id' => $this->tenant_id,
            'type' => $this->type,
            //'sham_cash_account_barcode' => $restaurant->sham_cach_account_barcode,
            //'sham_cash_account_id' => $restaurant->sham_cash_account_id,
            'paied' => ($this->payment)? true : false ,
            'status' => $this->status,
            'status_label' => __('orders.statuses.' . $this->status),
            'total' => $this->total,
            'placed_at' => $this->placed_at?->toIso8601String(),
        ];
    }
}
