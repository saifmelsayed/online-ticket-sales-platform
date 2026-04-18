<?php

namespace App\Services;

use App\Models\BookingItem;
use App\Models\Event;
use App\Models\SeatReservation;
use App\Models\TicketTier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OrganizerEventService
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    public function createEvent(array $data, int $organizerUserId): Event
    {
        $payload = [
            'organizer_id' => $organizerUserId,
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'event_datetime' => $data['event_datetime'],
            'is_online' => $data['is_online'],
            'venue_name' => $data['is_online'] ? null : ($data['venue_name'] ?? null),
            'venue_address' => $data['is_online'] ? null : ($data['venue_address'] ?? null),
            'status' => 'upcoming',
        ];

        if (!empty($data['banner_image']) && $data['banner_image']->isValid()) {
            $payload['banner_image'] = $data['banner_image']->store('event-banners', 'public');
        }

        $event = Event::create($payload);

        return $event->load(['category', 'tiers', 'organizer']);
    }

    public function listMyEvents(int $organizerUserId)
    {
        return Event::with(['tiers', 'category', 'organizer'])
            ->where('organizer_id', $organizerUserId)
            ->latest()
            ->get();
    }

    public function updateEvent(int $eventId, array $data, int $organizerUserId): Event
    {
        $event = Event::where('organizer_id', $organizerUserId)->findOrFail($eventId);

        $update = collect($data)->only([
            'category_id', 'name', 'description', 'event_datetime', 'status',
        ])->filter(fn ($v) => $v !== null)->all();

        if (array_key_exists('is_online', $data)) {
            $update['is_online'] = $data['is_online'];
        }

        $isOnline = $update['is_online'] ?? $event->is_online;

        if ($isOnline) {
            $update['venue_name'] = null;
            $update['venue_address'] = null;
        } else {
            if (array_key_exists('venue_name', $data)) {
                $update['venue_name'] = $data['venue_name'];
            }
            if (array_key_exists('venue_address', $data)) {
                $update['venue_address'] = $data['venue_address'];
            }
        }

        if (!empty($data['banner_image']) && $data['banner_image']->isValid()) {
            if ($event->banner_image) {
                Storage::disk('public')->delete($event->banner_image);
            }
            $update['banner_image'] = $data['banner_image']->store('event-banners', 'public');
        }

        $event->update($update);

        return $event->fresh()->load(['category', 'tiers', 'organizer']);
    }

    public function addTier(int $eventId, array $tierData, int $organizerUserId): TicketTier
    {
        $event = Event::where('organizer_id', $organizerUserId)->findOrFail($eventId);

        return TicketTier::create([
            'event_id' => $event->id,
            'name' => $tierData['name'],
            'base_price' => $tierData['base_price'],
            'total_seats' => $tierData['total_seats'],
            'sale_starts_at' => $tierData['sale_starts_at'],
            'sale_ends_at' => $tierData['sale_ends_at'],
            'sold_count' => 0,
            'version' => 1,
        ]);
    }

    public function updateTier(int $eventId, int $tierId, array $data, int $organizerUserId): TicketTier
    {
        $event = Event::where('organizer_id', $organizerUserId)->findOrFail($eventId);
        $tier = TicketTier::where('event_id', $event->id)->findOrFail($tierId);

        if (array_key_exists('total_seats', $data)) {
            $activeReserved = (int) SeatReservation::query()
                ->where('ticket_tier_id', $tier->id)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->sum('quantity');

            $minSeats = (int) $tier->sold_count + $activeReserved;
            if ((int) $data['total_seats'] < $minSeats) {
                throw ValidationException::withMessages([
                    'total_seats' => ["Total seats cannot be less than sold ({$tier->sold_count}) plus active holds ({$activeReserved})."],
                ]);
            }
        }

        $saleStarts = array_key_exists('sale_starts_at', $data)
            ? Carbon::parse($data['sale_starts_at'])
            : Carbon::parse($tier->sale_starts_at);
        $saleEnds = array_key_exists('sale_ends_at', $data)
            ? Carbon::parse($data['sale_ends_at'])
            : Carbon::parse($tier->sale_ends_at);

        if ($saleEnds->lte($saleStarts)) {
            throw ValidationException::withMessages([
                'sale_ends_at' => ['Sale end must be after sale start.'],
            ]);
        }

        $update = collect($data)->only([
            'name', 'base_price', 'total_seats', 'sale_starts_at', 'sale_ends_at',
        ])->filter(fn ($v) => $v !== null)->all();

        if ($update !== []) {
            $tier->update($update);
        }

        return $tier->fresh();
    }

    public function deleteTier(int $eventId, int $tierId, int $organizerUserId): void
    {
        $event = Event::where('organizer_id', $organizerUserId)->findOrFail($eventId);
        $tier = TicketTier::where('event_id', $event->id)->findOrFail($tierId);

        if ((int) $tier->sold_count > 0) {
            throw ValidationException::withMessages([
                'tier' => ['Cannot delete a tier that has sold tickets.'],
            ]);
        }

        $hasConfirmedSales = BookingItem::query()
            ->where('ticket_tier_id', $tier->id)
            ->whereHas('booking', fn ($q) => $q->where('status', 'confirmed'))
            ->exists();

        if ($hasConfirmedSales) {
            throw ValidationException::withMessages([
                'tier' => ['Cannot delete a tier with confirmed bookings.'],
            ]);
        }

        $tier->delete();
    }

    public function cancelOwnEvent(int $eventId, int $organizerUserId): Event
    {
        $event = Event::findOrFail($eventId);

        if ((int) $event->organizer_id !== (int) $organizerUserId) {
            throw new \InvalidArgumentException('You can only cancel your own events.');
        }

        return $this->adminService->cancelEventWithRefunds($eventId);
    }
}
