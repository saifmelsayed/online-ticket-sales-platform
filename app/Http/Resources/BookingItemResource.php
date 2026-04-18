<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tier = $this->relationLoaded('tier') ? $this->tier : null;

        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'line_total' => number_format((float) $this->unit_price * (int) $this->quantity, 2, '.', ''),
            'tier' => $tier ? [
                'id' => $tier->id,
                'name' => $tier->name,
                'base_price' => (string) $tier->base_price,
            ] : null,
            'e_ticket' => $this->relationLoaded('eTicket') && $this->eTicket ? [
                'id' => $this->eTicket->id,
                'qr_token' => $this->eTicket->qr_token,
                'file_path' => $this->eTicket->file_path,
            ] : null,
        ];
    }
}
