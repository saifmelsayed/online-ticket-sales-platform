<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Cart;
use App\Models\ETicket;
use App\Models\Event;
use App\Models\SeatReservation;
use App\Models\TicketTier;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public const FEE_MULTIPLIER = 1.01;

    public static function finalUnitCredits(float|string $basePrice): string
    {
        return number_format(round((float) $basePrice * self::FEE_MULTIPLIER, 2), 2, '.', '');
    }

    public function checkout(User $user, string $idempotencyKey): Booking
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => ['Idempotency key is required.'],
            ]);
        }

        return DB::transaction(function () use ($user, $idempotencyKey) {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existing = Booking::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $lockedUser->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['Invalid idempotency key.'],
                    ]);
                }

                return $existing->load(['items.tier', 'items.eTicket', 'event']);
            }

            $cart = Cart::query()
                ->where('user_id', $lockedUser->id)
                ->with(['items.tier.event'])
                ->first();

            if (!$cart || $cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['Cart is empty.'],
                ]);
            }

            $eventIds = $cart->items->pluck('tier.event_id')->filter()->unique()->values();
            if ($eventIds->count() !== 1) {
                throw ValidationException::withMessages([
                    'cart' => ['Checkout supports one event at a time. Adjust your cart and try again.'],
                ]);
            }

            $event = Event::query()->findOrFail((int) $eventIds->first());
            $this->assertPurchasableEvent($event);

            $lines = [];
            foreach ($cart->items->sortBy('ticket_tier_id') as $cartItem) {
                $tier = $cartItem->tier;
                if (!$tier) {
                    throw ValidationException::withMessages([
                        'cart' => ['Invalid cart line.'],
                    ]);
                }

                $this->assertTierSaleOpen($tier);
                $qty = (int) $cartItem->quantity;

                $reservation = SeatReservation::query()
                    ->where('user_id', $lockedUser->id)
                    ->where('ticket_tier_id', $tier->id)
                    ->where('status', 'pending')
                    ->where('expires_at', '>', now())
                    ->first();

                if (!$reservation || (int) $reservation->quantity !== $qty) {
                    throw ValidationException::withMessages([
                        'cart' => ['Seat hold expired or out of sync. Refresh your cart and try again.'],
                    ]);
                }

                $unitFinal = self::finalUnitCredits($tier->base_price);
                $lineTotal = round((float) $unitFinal * $qty, 2);

                $lines[] = [
                    'tier_id' => $tier->id,
                    'quantity' => $qty,
                    'unit_price' => $unitFinal,
                    'line_total' => $lineTotal,
                ];
            }

            $total = round(array_sum(array_column($lines, 'line_total')), 2);

            if ((float) $lockedUser->credit_balance < $total) {
                throw ValidationException::withMessages([
                    'cart' => ['Insufficient credits to complete this purchase.'],
                ]);
            }

            foreach ($lines as $line) {
                $this->incrementTierSoldWithOptimisticLock($lockedUser->id, $line['tier_id'], $line['quantity']);
            }

            $lockedUser->decrement('credit_balance', $total);

            try {
                $booking = Booking::create([
                    'user_id' => $lockedUser->id,
                    'event_id' => $event->id,
                    'total_credits' => number_format($total, 2, '.', ''),
                    'status' => 'confirmed',
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (QueryException $e) {
                if ($this->isUniqueIdempotencyViolation($e)) {
                    $winner = Booking::query()->where('idempotency_key', $idempotencyKey)->first();
                    if (!$winner || (int) $winner->user_id !== (int) $lockedUser->id) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => ['Checkout conflict. Please use a new idempotency key.'],
                        ]);
                    }

                    return $winner->load(['items.tier', 'items.eTicket', 'event']);
                }
                throw $e;
            }

            foreach ($lines as $line) {
                $item = BookingItem::create([
                    'booking_id' => $booking->id,
                    'ticket_tier_id' => $line['tier_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                ]);

                ETicket::create([
                    'booking_item_id' => $item->id,
                    'qr_token' => (string) Str::uuid(),
                    'file_path' => null,
                ]);
            }

            Transaction::create([
                'user_id' => $lockedUser->id,
                'amount' => number_format($total, 2, '.', ''),
                'type' => 'debit',
                'reference' => 'booking:'.$booking->id,
            ]);

            $tierIds = array_column($lines, 'tier_id');
            SeatReservation::query()
                ->where('user_id', $lockedUser->id)
                ->whereIn('ticket_tier_id', $tierIds)
                ->where('status', 'pending')
                ->update(['status' => 'confirmed']);

            $cart->items()->delete();

            return $booking->load(['items.tier', 'items.eTicket', 'event']);
        });
    }

    private function assertPurchasableEvent(Event $event): void
    {
        if ($event->status === 'cancelled') {
            throw ValidationException::withMessages([
                'cart' => ['This event is no longer available for purchase.'],
            ]);
        }

        if (Carbon::parse($event->event_datetime)->lte(now())) {
            throw ValidationException::withMessages([
                'cart' => ['This event has already started or ended.'],
            ]);
        }
    }

    private function assertTierSaleOpen(TicketTier $tier): void
    {
        $saleStarts = Carbon::parse($tier->sale_starts_at);
        $saleEnds = Carbon::parse($tier->sale_ends_at);

        if (now()->lt($saleStarts) || now()->gt($saleEnds)) {
            throw ValidationException::withMessages([
                'cart' => ['One or more ticket tiers are outside their sale window.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function incrementTierSoldWithOptimisticLock(int $userId, int $tierId, int $quantity): void
    {
        /** @var TicketTier $tier */
        $tier = TicketTier::query()->whereKey($tierId)->lockForUpdate()->firstOrFail();

        $othersPending = (int) SeatReservation::query()
            ->where('ticket_tier_id', $tier->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->where('user_id', '!=', $userId)
            ->sum('quantity');

        if ($tier->sold_count + $othersPending + $quantity > $tier->total_seats) {
            throw ValidationException::withMessages([
                'cart' => ['Not enough seats remain for one of the selected tiers.'],
            ]);
        }

        $updated = TicketTier::query()
            ->whereKey($tier->id)
            ->where('version', $tier->version)
            ->whereRaw('sold_count + ? <= total_seats', [$quantity])
            ->update([
                'sold_count' => DB::raw('sold_count + '.((int) $quantity)),
                'version' => DB::raw('version + 1'),
            ]);

        if ($updated === 0) {
            throw ValidationException::withMessages([
                'cart' => ['Inventory changed while checking out. Please refresh and try again.'],
            ]);
        }
    }

    private function isUniqueIdempotencyViolation(QueryException $e): bool
    {
        if (($e->errorInfo[0] ?? null) === '23000') {
            return true;
        }

        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'idempotency')
            || str_contains($msg, 'unique constraint');
    }
}
