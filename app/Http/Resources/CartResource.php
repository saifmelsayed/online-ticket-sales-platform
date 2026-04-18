<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cart = $this->resource;
        $cart->loadMissing(['items.tier.event']);

        $lineItems = $cart->relationLoaded('items') ? $cart->items : collect();
        $items = CartItemResource::collection($lineItems);

        $subtotal = 0.0;
        foreach ($lineItems as $item) {
            $item->loadMissing('tier');
            if ($item->tier) {
                $subtotal += (float) $item->tier->base_price * (int) $item->quantity;
            }
        }

        return [
            'id' => $cart->id,
            'items' => $items,
            'total_quantity' => (int) ($lineItems->sum('quantity')),
            'estimated_subtotal_credits' => number_format($subtotal, 2, '.', ''),
        ];
    }
}
