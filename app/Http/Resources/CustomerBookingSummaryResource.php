<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Booking */
class CustomerBookingSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total_credits' => (string) $this->total_credits,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'name' => $this->event->name,
                'event_datetime' => $this->event->event_datetime,
                'is_online' => (bool) $this->event->is_online,
                'venue_name' => $this->event->venue_name,
            ]),
            'items' => BookingItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
