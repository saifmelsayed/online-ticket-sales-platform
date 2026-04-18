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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSystemTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'credit_balance' => 0,
            'is_active' => true,
        ]);
    }

    public function test_admin_overview_returns_metrics(): void
    {
        User::factory()->count(3)->create(['role' => 'customer', 'is_active' => true]);
        $organizer = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $category = Category::create(['name' => 'X']);
        for ($i = 0; $i < 2; $i++) {
            Event::create([
                'organizer_id' => $organizer->id,
                'category_id' => $category->id,
                'name' => 'E'.$i,
                'description' => 'd',
                'event_datetime' => now()->addMonth(),
                'is_online' => true,
                'status' => 'upcoming',
            ]);
        }

        Sanctum::actingAs($this->actingAdmin());

        $this->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_registered_users', 5)
            ->assertJsonPath('data.total_events_listed', 2)
            ->assertJsonPath('data.total_tickets_sold', 0)
            ->assertJsonPath('data.system_revenue_credits', '0.00');
    }

    public function test_admin_overview_counts_confirmed_ticket_quantity(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $category = Category::create(['name' => 'Y']);
        $event = Event::create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'name' => 'Show',
            'description' => 'd',
            'event_datetime' => now()->addMonth(),
            'is_online' => true,
            'status' => 'upcoming',
        ]);
        $tier = TicketTier::create([
            'event_id' => $event->id,
            'name' => 'GA',
            'base_price' => 100,
            'total_seats' => 10,
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
            'quantity' => 2,
        ]);
        SeatReservation::create([
            'user_id' => $customer->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 2,
            'reserved_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'status' => 'pending',
        ]);

        $token = $customer->createToken('t')->plainTextToken;
        $this->withToken($token)
            ->postJson('/api/checkout', ['idempotency_key' => 'admin-metric-1'])
            ->assertOk();

        Sanctum::actingAs($this->actingAdmin());

        $this->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.total_tickets_sold', 2)
            ->assertJsonPath('data.system_revenue_credits', '2.00');
    }

    public function test_admin_can_cancel_event_and_refunds_customers(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $category = Category::create(['name' => 'Z']);
        $event = Event::create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'name' => 'Cancel Me',
            'description' => 'd',
            'event_datetime' => now()->addMonth(),
            'is_online' => true,
            'status' => 'upcoming',
        ]);
        $tier = TicketTier::create([
            'event_id' => $event->id,
            'name' => 'GA',
            'base_price' => 50,
            'total_seats' => 10,
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
            'quantity' => 1,
        ]);
        SeatReservation::create([
            'user_id' => $customer->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'reserved_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'status' => 'pending',
        ]);

        $this->withToken($customer->createToken('t')->plainTextToken)
            ->postJson('/api/checkout', ['idempotency_key' => 'cancel-flow'])
            ->assertOk();

        $balanceAfterPurchase = (float) $customer->fresh()->credit_balance;

        Sanctum::actingAs($this->actingAdmin());

        $this->patchJson('/api/admin/events/'.$event->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $event->fresh()->status);
        $this->assertSame('cancelled', Booking::first()->fresh()->status);
        $this->assertGreaterThan($balanceAfterPurchase, (float) $customer->fresh()->credit_balance);
    }

    public function test_non_admin_cannot_access_overview(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        Sanctum::actingAs($customer);

        $this->getJson('/api/admin/overview')->assertForbidden();
    }
}
