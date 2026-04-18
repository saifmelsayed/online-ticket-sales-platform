<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ETicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserDashboardService
{
    public function getDashboardPayload(User $user): array
    {
        $user->refresh();

        $upcoming = Booking::query()
            ->where('bookings.user_id', $user->id)
            ->where('bookings.status', 'confirmed')
            ->whereHas('event', fn ($q) => $q->where('event_datetime', '>', now()))
            ->with(['event', 'items.tier', 'items.eTicket'])
            ->join('events', 'events.id', '=', 'bookings.event_id')
            ->orderBy('events.event_datetime')
            ->select('bookings.*')
            ->limit(20)
            ->get();

        $recent = Booking::query()
            ->where('user_id', $user->id)
            ->with(['event', 'items.tier', 'items.eTicket'])
            ->latest()
            ->limit(10)
            ->get();

        return [
            'user' => $user,
            'upcoming_bookings' => $upcoming,
            'recent_bookings' => $recent,
        ];
    }

    public function paginateBookings(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Booking::query()
            ->where('user_id', $user->id)
            ->with(['event', 'items.tier', 'items.eTicket'])
            ->latest()
            ->paginate($perPage);
    }

    public function findBookingForUser(User $user, int $bookingId): ?Booking
    {
        return Booking::query()
            ->where('user_id', $user->id)
            ->with(['event', 'items.tier', 'items.eTicket'])
            ->find($bookingId);
    }

    /**
     * E-ticket for download (ownership enforced).
     */
    public function findETicketForUser(User $user, int $eTicketId): ?ETicket
    {
        $ticket = ETicket::query()
            ->whereKey($eTicketId)
            ->with([
                'bookingItem.booking',
                'bookingItem.tier',
            ])
            ->first();

        if (!$ticket || !$ticket->bookingItem || !$ticket->bookingItem->booking) {
            return null;
        }

        if ((int) $ticket->bookingItem->booking->user_id !== (int) $user->id) {
            return null;
        }

        $ticket->bookingItem->booking->loadMissing('event');

        return $ticket;
    }

    public function buildEticketPlainText(ETicket $ticket): string
    {
        $item = $ticket->bookingItem;
        $booking = $item->booking;
        $event = $booking->event;
        $tier = $item->tier;

        $venueLine = $event && $event->is_online
            ? 'Format: Online'
            : 'Venue: '.trim(($event->venue_name ?? '').' '.($event->venue_address ?? ''));

        $lines = [
            'ONLINE TICKET',
            '================',
            'Booking #'.$booking->id,
            'Status: '.$booking->status,
            '',
            'Event: '.($event->name ?? ''),
            'When: '.($event ? Carbon::parse($event->event_datetime)->toIso8601String() : ''),
            $venueLine,
            '',
            'Tier: '.($tier->name ?? ''),
            'Quantity: '.$item->quantity,
            'Unit price (incl. fee): '.$item->unit_price.' credits',
            '',
            'QR token: '.$ticket->qr_token,
            '',
            'Present this token at check-in.',
        ];

        return implode("\n", $lines)."\n";
    }
}
