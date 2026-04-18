<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'event_datetime' => $this->event_datetime,
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'is_online' => (bool) $this->is_online,
            'banner_image' => $this->banner_image,
            'status' => $this->status,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'organizer' => $this->whenLoaded('organizer', fn () => [
                'id' => $this->organizer->id,
                'name' => $this->organizer->name,
            ]),
            'tiers' => CatalogTicketTierResource::collection($this->whenLoaded('tiers')),
        ];
    }
}
