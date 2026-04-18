<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Event;
use App\Models\SeatReservation;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function makeTierForUpcomingEvent(int $totalSeats = 100): TicketTier
    {
        $organizer = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $category = Category::create(['name' => 'Music']);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'name' => 'Live Show',
            'description' => 'Test',
            'event_datetime' => now()->addMonth(),
            'is_online' => false,
            'venue_name' => 'Hall',
            'venue_address' => 'St',
            'status' => 'upcoming',
        ]);

        return TicketTier::create([
            'event_id' => $event->id,
            'name' => 'GA',
            'base_price' => 50,
            'total_seats' => $totalSeats,
            'sold_count' => 0,
            'sale_starts_at' => now()->subDay(),
            'sale_ends_at' => now()->addMonth(),
            'version' => 1,
        ]);
    }

       public function test_customer_can_sync_cart_and_creates_pending_reservation(): void
    {
        $tier = $this->makeTierForUpcomingEvent();
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $token = $customer->createToken('t')->plainTextToken;

        $response = $this->withToken($token)
            ->putJson('/api/cart/items', [
                'ticket_tier_id' => $tier->id,
                'quantity' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reservation.quantity', 2)
            ->assertJsonPath('data.cart.total_quantity', 2)
            ->assertJsonPath('data.cart.items.0.ticket_tier.id', $tier->id)
            ->assertJsonPath('data.cart.items.0.ticket_tier.name', 'GA');

        $this->assertDatabaseHas('seat_reservations', [
            'user_id' => $customer->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 2,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('cart_items', [
            'ticket_tier_id' => $tier->id,
            'quantity' => 2,
        ]);
    }

    public function test_get_cart_includes_nested_ticket_tier_and_subtotal(): void
    {
        $tier = $this->makeTierForUpcomingEvent();
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $token = $customer->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/cart/items', [
                'ticket_tier_id' => $tier->id,
                'quantity' => 3,
            ])
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.ticket_tier.id', $tier->id)
            ->assertJsonPath('data.items.0.line_subtotal_credits', '150.00')
            ->assertJsonPath('data.estimated_subtotal_credits', '150.00');
    }

    public function test_organizer_cannot_access_cart(): void
    {
        $tier = $this->makeTierForUpcomingEvent();
        $organizer = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $token = $organizer->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/cart/items', [
                'ticket_tier_id' => $tier->id,
                'quantity' => 1,
            ])
            ->assertForbidden();
    }

    public function test_cannot_reserve_more_than_available_with_other_pending_holds(): void
    {
        $tier = $this->makeTierForUpcomingEvent(5);
        $other = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        SeatReservation::create([
            'user_id' => $other->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 4,
            'reserved_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'status' => 'pending',
        ]);

        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $token = $customer->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/cart/items', [
                'ticket_tier_id' => $tier->id,
                'quantity' => 2,
            ])
            ->assertStatus(422);
    }

    public function test_sync_quantity_zero_removes_line_and_expires_hold(): void
    {
        $tier = $this->makeTierForUpcomingEvent();
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $token = $customer->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/cart/items', [
                'ticket_tier_id' => $tier->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $cartId = $customer->cart()->first()->id;

        $this->withToken($token)
            ->putJson('/api/cart/items', [
                'ticket_tier_id' => $tier->id,
                'quantity' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.cart.total_quantity', 0);

        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cartId,
            'ticket_tier_id' => $tier->id,
        ]);

        $this->assertDatabaseHas('seat_reservations', [
            'user_id' => $customer->id,
            'ticket_tier_id' => $tier->id,
            'status' => 'expired',
        ]);
    }

    public function test_expire_command_clears_stale_holds_and_cart_lines(): void
    {
        $tier = $this->makeTierForUpcomingEvent();
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $cart = $customer->cart()->create([]);
        CartItem::create([
            'cart_id' => $cart->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
        ]);
        SeatReservation::create([
            'user_id' => $customer->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'reserved_at' => now()->subMinutes(20),
            'expires_at' => now()->subMinute(),
            'status' => 'pending',
        ]);

        Artisan::call('reservations:expire');

        $this->assertDatabaseHas('seat_reservations', [
            'user_id' => $customer->id,
            'ticket_tier_id' => $tier->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cart->id,
            'ticket_tier_id' => $tier->id,
        ]);
    }
}
