<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CatalogService
{
    public function listEvents(Request $request)
    {
        $perPage = min(50, max(1, (int) $request->input('per_page', 15)));

        $query = Event::query()
            ->with(['category', 'organizer', 'tiers.event', 'tiers.reservations'])
            ->where('status', '!=', 'cancelled');

        // Default: only future events (project plan: upcoming catalog). Use ?include_past=1 to include past dates.
        if (!$request->boolean('include_past')) {
            $query->where('event_datetime', '>', now());
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->string('name').'%');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('event_datetime', '>=', $request->date('date_from')->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('event_datetime', '<=', $request->date('date_to')->endOfDay());
        }

        // Only filter when the client explicitly sends is_online (avoids empty ?is_online= wiping results).
        if ($request->filled('is_online')) {
            $query->where('is_online', $request->boolean('is_online'));
        }

        if ($request->filled('venue')) {
            $v = '%'.$request->string('venue').'%';
            $query->where(function (Builder $q) use ($v) {
                $q->where('venue_name', 'like', $v)->orWhere('venue_address', 'like', $v);
            });
        }

        if ($request->filled('organizer_id')) {
            $query->where('organizer_id', $request->integer('organizer_id'));
        }

        if ($request->filled('price_min') || $request->filled('price_max')) {
            $query->whereHas('tiers', function (Builder $q) use ($request) {
                if ($request->filled('price_min')) {
                    $q->where('base_price', '>=', $request->input('price_min'));
                }
                if ($request->filled('price_max')) {
                    $q->where('base_price', '<=', $request->input('price_max'));
                }
            });
        }

        if ($request->filled('has_availability') && $request->boolean('has_availability')) {
            $query->whereHas('tiers', function (Builder $q) {
                $q->whereRaw(
                    '(ticket_tiers.total_seats - ticket_tiers.sold_count - (
                        SELECT COALESCE(SUM(quantity), 0) FROM seat_reservations
                        WHERE seat_reservations.ticket_tier_id = ticket_tiers.id
                        AND seat_reservations.status = ?
                        AND seat_reservations.expires_at > ?
                    )) > 0',
                    ['pending', now()]
                );
            });
        }

        return $query->orderBy('event_datetime')->paginate($perPage);
    }

    public function getEvent(int $id): Event
    {
        return Event::with(['category', 'organizer', 'tiers.event', 'tiers.reservations'])
            ->findOrFail($id);
    }
}
