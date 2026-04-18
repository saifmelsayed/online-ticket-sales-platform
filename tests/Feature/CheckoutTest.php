<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Event;
use App\Models\SeatReservation;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function seedCheckoutScenario(float $basePrice = 100.00, int $qty = 1): array
    {
        $organizer = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $category = Category::create(['name' => 'Gigs']);
        $event = Event::create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'name' => 'Big Show',
            'description' => 'x',
            'event_datetime' => now()->addMonth(),
            'is_online' => false,
            'venue_name' => 'Hall',
            'venue_address' => 'St',
            'status' => 'upcoming',
        ]);
        $tier = TicketTier::create([
            'event_id' => $event->id,
            'name' => 'GA',
            'base_price' => $basePrice,
            'total_seats' => 50,
            'sold_count' => 0,
            'sale_starts_at' => now()->subDay(),
            'sale_ends_at' => now()->addMonth(),
            'version' => 1,
        ]);

        $customer = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
            'credit_balance' => 5000,
        ]);

        $cart = $customer->cart()->create([]);
        CartItem::create([
            'cart_id' => $cart->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => $qty,
        ]);
        SeatReservation::create([
            'user_id' => $customer->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => $qty,
            'reserved_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'status' => 'pending',
        ]);

        $token = $customer->createToken('t')->plainTextToken;

        return compact('customer', 'event', 'tier', 'token', 'qty', 'basePrice');
    }

    public function test_checkout_debits_credits_and_creates_booking(): void
    {
        $ctx = $this->seedCheckoutScenario(100, 2);
        $unitFinal = '101.00';
        $total = '202.00';

        $response = $this->withToken($ctx['token'])
            ->postJson('/api/checkout', ['idempotency_key' => 'pay-1']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_credits', $total)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', $unitFinal)
            ->assertJsonStructure(['data' => ['items' => [['e_ticket' => ['qr_token']]]]]);

        $this->assertSame(5000 - 202, (int) $ctx['customer']->fresh()->credit_balance);

        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertSame($total, (string) $booking->total_credits);

        $ctx['tier']->refresh();
        $this->assertSame(2, $ctx['tier']->sold_count);

        $this->assertDatabaseHas('seat_reservations', [
            'user_id' => $ctx['customer']->id,
            'ticket_tier_id' => $ctx['tier']->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'ticket_tier_id' => $ctx['tier']->id,
        ]);
    }

    public function test_idempotent_checkout_does_not_double_charge(): void
    {
        $ctx = $this->seedCheckoutScenario(50, 1);
        $key = 'idem-xyz';

        $first = $this->withToken($ctx['token'])
            ->postJson('/api/checkout', ['idempotency_key' => $key]);
        $first->assertOk();
        $id = $first->json('data.id');

        $second = $this->withToken($ctx['token'])
            ->postJson('/api/checkout', ['idempotency_key' => $key]);
        $second->assertOk()->assertJsonPath('data.id', $id);

        $this->assertSame(1, Booking::count());
        $this->assertSame(4949.5, (float) $ctx['customer']->fresh()->credit_balance);
    }

    public function test_checkout_fails_when_insufficient_credits(): void
    {
        $ctx = $this->seedCheckoutScenario(1000, 5);
        $ctx['customer']->update(['credit_balance' => 100]);

        $this->withToken($ctx['token'])
            ->postJson('/api/checkout', ['idempotency_key' => 'poor'])
            ->assertStatus(422);

        $this->assertSame(0, Booking::count());
    }

    public function test_checkout_fails_when_cart_spans_multiple_events(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $category = Category::create(['name' => 'Mix']);
        $e1 = Event::create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'name' => 'A',
            'description' => 'x',
            'event_datetime' => now()->addMonth(),
            'is_online' => true,
            'status' => 'upcoming',
        ]);
        $e2 = Event::create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'name' => 'B',
            'description' => 'x',
            'event_datetime' => now()->addMonths(2),
            'is_online' => true,
            'status' => 'upcoming',
        ]);
        $t1 = TicketTier::create([
            'event_id' => $e1->id, 'name' => 'GA', 'base_price' => 10,
            'total_seats' => 10, 'sold_count' => 0,
            'sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addMonth(), 'version' => 1,
        ]);
        $t2 = TicketTier::create([
            'event_id' => $e2->id, 'name' => 'GA', 'base_price' => 10,
            'total_seats' => 10, 'sold_count' => 0,
            'sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addMonth(), 'version' => 1,
        ]);

        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'credit_balance' => 5000]);
        $cart = $customer->cart()->create([]);
        CartItem::create(['cart_id' => $cart->id, 'ticket_tier_id' => $t1->id, 'quantity' => 1]);
        CartItem::create(['cart_id' => $cart->id, 'ticket_tier_id' => $t2->id, 'quantity' => 1]);
        foreach ([$t1, $t2] as $t) {
            SeatReservation::create([
                'user_id' => $customer->id,
                'ticket_tier_id' => $t->id,
                'quantity' => 1,
                'reserved_at' => now(),
                'expires_at' => now()->addMinutes(10),
                'status' => 'pending',
            ]);
        }

        $token = $customer->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/checkout', ['idempotency_key' => 'multi'])
            ->assertStatus(422);
    }

    public function test_checkout_fails_when_hold_expired(): void
    {
        $ctx = $this->seedCheckoutScenario(10, 1);
        SeatReservation::query()->where('user_id', $ctx['customer']->id)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->withToken($ctx['token'])
            ->postJson('/api/checkout', ['idempotency_key' => 'late'])
            ->assertStatus(422);
    }

    public function test_organizer_cannot_checkout(): void
    {
        $ctx = $this->seedCheckoutScenario();
        $org = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $token = $org->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/checkout', ['idempotency_key' => 'x'])
            ->assertForbidden();
    }
}
