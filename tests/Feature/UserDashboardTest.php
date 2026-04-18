<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\ETicket;
use App\Models\Event;
use App\Models\SeatReservation;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{customer: User, token: string, eTicket: ETicket, booking: Booking} */
    private function customerWithCompletedPurchase(): array
    {
        $organizer = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $category = Category::create(['name' => 'Shows']);
        $event = Event::create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'name' => 'Concert',
            'description' => 'x',
            'event_datetime' => now()->addMonth(),
            'is_online' => false,
            'venue_name' => 'Arena',
            'venue_address' => '1 St',
            'status' => 'upcoming',
        ]);
        $tier = TicketTier::create([
            'event_id' => $event->id,
            'name' => 'GA',
            'base_price' => 100,
            'total_seats' => 20,
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

        $token = $customer->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/checkout', ['idempotency_key' => 'dash-test-1'])
            ->assertOk();

        $booking = Booking::first();
        $eTicket = ETicket::first();

        return compact('customer', 'token', 'eTicket', 'booking');
    }

    public function test_dashboard_returns_balance_and_bookings(): void
    {
        $ctx = $this->customerWithCompletedPurchase();

        $this->withToken($ctx['token'])
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.credit_balance', (string) $ctx['customer']->fresh()->credit_balance)
            ->assertJsonPath('data.upcoming_bookings.0.id', $ctx['booking']->id)
            ->assertJsonPath('data.recent_bookings.0.id', $ctx['booking']->id);
    }

    public function test_bookings_index_is_paginated(): void
    {
        $ctx = $this->customerWithCompletedPurchase();

        $this->withToken($ctx['token'])
            ->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.bookings.0.id', $ctx['booking']->id);
    }

    public function test_booking_show_returns_full_booking(): void
    {
        $ctx = $this->customerWithCompletedPurchase();

        $this->withToken($ctx['token'])
            ->getJson('/api/bookings/'.$ctx['booking']->id)
            ->assertOk()
            ->assertJsonPath('data.id', $ctx['booking']->id)
            ->assertJsonStructure(['data' => ['items' => [['e_ticket' => ['qr_token']]]]]);
    }

    public function test_booking_show_404_for_other_user(): void
    {
        $ctx = $this->customerWithCompletedPurchase();
        $other = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $otherToken = $other->createToken('t')->plainTextToken;

        $this->withToken($otherToken)
            ->getJson('/api/bookings/'.$ctx['booking']->id)
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_eticket_download_for_owner(): void
    {
        $ctx = $this->customerWithCompletedPurchase();

        $response = $this->withToken($ctx['token'])
            ->get('/api/e-tickets/'.$ctx['eTicket']->id.'/download');

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString($ctx['eTicket']->qr_token, $response->getContent());
        $this->assertStringContainsString('QR token:', $response->getContent());
    }

    public function test_eticket_download_denied_for_other_user(): void
    {
        $ctx = $this->customerWithCompletedPurchase();
        $other = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $otherToken = $other->createToken('t')->plainTextToken;

        $this->withToken($otherToken)
            ->get('/api/e-tickets/'.$ctx['eTicket']->id.'/download')
            ->assertNotFound();
    }

    public function test_organizer_cannot_access_dashboard(): void
    {
        $org = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $token = $org->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/dashboard')->assertForbidden();
    }
}
