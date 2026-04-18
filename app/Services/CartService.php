<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Event;
use App\Models\SeatReservation;
use App\Models\TicketTier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public const RESERVATION_TTL_MINUTES = 10;

    public function getCartForUser(User $user): Cart
    {
        $cart = Cart::query()->firstOrCreate(
            ['user_id' => $user->id],
            []
        );
        $cart->loadMissing(['items.tier.event']);

        return $cart;
    }

    /**
     * Set line quantity for a tier. Quantity 0 removes the line and expires the hold.
     *
     * @return array{cart: Cart, reservation: SeatReservation|null}
     */
    public function syncCartItem(User $user, int $ticketTierId, int $quantity): array
    {
        return DB::transaction(function () use ($user, $ticketTierId, $quantity) {
            /** @var TicketTier $tier */
            $tier = TicketTier::query()->lockForUpdate()->find($ticketTierId);
            if (!$tier) {
                throw ValidationException::withMessages([
                    'ticket_tier_id' => ['Ticket tier not found.'],
                ]);
            }

            $tier->load('event');
            $event = $tier->event;
            if (!$event instanceof Event) {
                throw ValidationException::withMessages([
                    'ticket_tier_id' => ['Invalid event for this tier.'],
                ]);
            }

            if ($event->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'ticket_tier_id' => ['This event is no longer available.'],
                ]);
            }

            if (Carbon::parse($event->event_datetime)->lte(now())) {
                throw ValidationException::withMessages([
                    'ticket_tier_id' => ['This event has already started or ended.'],
                ]);
            }

            $saleStarts = Carbon::parse($tier->sale_starts_at);
            $saleEnds = Carbon::parse($tier->sale_ends_at);
            if (now()->lt($saleStarts)) {
                throw ValidationException::withMessages([
                    'ticket_tier_id' => ['Sales for this tier have not started yet.'],
                ]);
            }
            if (now()->gt($saleEnds)) {
                throw ValidationException::withMessages([
                    'ticket_tier_id' => ['Sales for this tier have ended.'],
                ]);
            }

            $cart = Cart::query()->firstOrCreate(['user_id' => $user->id], []);

            if ($quantity === 0) {
                CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->where('ticket_tier_id', $ticketTierId)
                    ->delete();

                SeatReservation::query()
                    ->where('user_id', $user->id)
                    ->where('ticket_tier_id', $ticketTierId)
                    ->where('status', 'pending')
                    ->update(['status' => 'expired']);

                return ['cart' => $cart->load(['items.tier.event']), 'reservation' => null];
            }

            $othersPending = (int) SeatReservation::query()
                ->where('ticket_tier_id', $ticketTierId)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->where('user_id', '!=', $user->id)
                ->sum('quantity');

            if ($tier->sold_count + $othersPending + $quantity > $tier->total_seats) {
                throw ValidationException::withMessages([
                    'quantity' => ['Not enough seats available for this tier.'],
                ]);
            }

            CartItem::query()->updateOrCreate(
                [
                    'cart_id' => $cart->id,
                    'ticket_tier_id' => $ticketTierId,
                ],
                ['quantity' => $quantity]
            );

            $now = now();
            $expiresAt = $now->copy()->addMinutes(self::RESERVATION_TTL_MINUTES);

            $reservation = SeatReservation::query()
                ->where('user_id', $user->id)
                ->where('ticket_tier_id', $ticketTierId)
                ->where('status', 'pending')
                ->where('expires_at', '>', $now)
                ->first();

            if ($reservation) {
                $reservation->update([
                    'quantity' => $quantity,
                    'reserved_at' => $now,
                    'expires_at' => $expiresAt,
                ]);
            } else {
                SeatReservation::query()
                    ->where('user_id', $user->id)
                    ->where('ticket_tier_id', $ticketTierId)
                    ->where('status', 'pending')
                    ->update(['status' => 'expired']);

                $reservation = SeatReservation::create([
                    'user_id' => $user->id,
                    'ticket_tier_id' => $ticketTierId,
                    'quantity' => $quantity,
                    'reserved_at' => $now,
                    'expires_at' => $expiresAt,
                    'status' => 'pending',
                ]);
            }

            return [
                'cart' => $cart->load(['items.tier.event']),
                'reservation' => $reservation->fresh(),
            ];
        });
    }
}
