<?php

namespace App\Services;

use App\Contracts\Repositories\OrganizerRepositoryInterface;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function __construct(
        protected OrganizerRepositoryInterface $organizers
    ) {}

    public function pendingOrganizers(): Collection
    {
        return $this->organizers->pendingWithUser();
    }

    public function approvedOrganizers(): Collection
    {
        return $this->organizers->approvedWithUser();
    }

    public function approveOrganizer(int $organizerId): void
    {
        $organizer = $this->organizers->findOrFail($organizerId);
        $organizer->update(['approval_status' => 'approved']);
    }

    public function rejectOrganizer(int $organizerId): void
    {
        $organizer = $this->organizers->findOrFail($organizerId);
        $organizer->update(['approval_status' => 'rejected']);
    }

    public function deactivateUser(int $userId, User $actor): void
    {
        $target = User::findOrFail($userId);

        if ($target->id === $actor->id) {
            throw new \InvalidArgumentException('You cannot deactivate your own account.');
        }

        if ($target->role === 'admin') {
            throw new \InvalidArgumentException('Cannot deactivate an admin account.');
        }

        $target->update(['is_active' => false]);
    }

    public function reactivateUser(int $userId): void
    {
        User::findOrFail($userId)->update(['is_active' => true]);
    }

    /**
     * Admin dashboard metrics (project plan): users, events, tickets sold, estimated system fee revenue.
     */
    public function systemOverview(): array
    {
        $totalUsers = User::query()->count();
        $totalEvents = Event::query()->count();

        $totalTicketsSold = (int) BookingItem::query()
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->where('bookings.status', 'confirmed')
            ->sum('booking_items.quantity');

        $rows = BookingItem::query()
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->where('bookings.status', 'confirmed')
            ->select(['booking_items.quantity', 'booking_items.unit_price'])
            ->get();

        $systemRevenue = 0.0;
        $multiplier = CheckoutService::FEE_MULTIPLIER;
        foreach ($rows as $row) {
            $qty = (int) $row->quantity;
            $finalUnit = (float) $row->unit_price;
            $baseUnit = round($finalUnit / $multiplier, 2);
            $systemRevenue += round($qty * ($finalUnit - $baseUnit), 2);
        }

        return [
            'total_registered_users' => $totalUsers,
            'total_events_listed' => $totalEvents,
            'total_tickets_sold' => $totalTicketsSold,
            'system_revenue_credits' => number_format($systemRevenue, 2, '.', ''),
        ];
    }

    /**
     * Cancel an event and refund all confirmed bookings (idempotent if already cancelled).
     */
    public function cancelEventWithRefunds(int $eventId): Event
    {
        return DB::transaction(function () use ($eventId) {
            $event = Event::lockForUpdate()->findOrFail($eventId);

            if ($event->status === 'cancelled') {
                return $event;
            }

            $event->update(['status' => 'cancelled']);

            $bookings = Booking::where('event_id', $event->id)
                ->where('status', 'confirmed')
                ->lockForUpdate()
                ->get();

            foreach ($bookings as $booking) {
                $user = User::lockForUpdate()->find($booking->user_id);
                if (!$user) {
                    continue;
                }

                $amount = (float) $booking->total_credits;
                $user->increment('credit_balance', $amount);

                $user->transactions()->create([
                    'amount' => $amount,
                    'type' => 'refund',
                    'reference' => 'booking:'.$booking->id,
                ]);

                $booking->update(['status' => 'cancelled']);
            }

            return $event->fresh();
        });
    }
}
