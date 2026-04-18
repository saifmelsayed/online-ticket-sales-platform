<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\SeatReservation;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_upcoming_events(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $category = Category::create(['name' => 'Music']);

        Event::create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'name' => 'Summer Fest',
            'description' => 'Live',
            'event_datetime' => now()->addMonth(),
            'is_online' => false,
            'venue_name' => 'Park',
            'venue_address' => 'Downtown',
            'status' => 'upcoming',
        ]);

        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.events');
    }

    public function test_guest_can_see_event_detail_with_tier_capacity(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $category = Category::create(['name' => 'Sports']);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'name' => 'Match',
            'description' => 'Final',
            'event_datetime' => now()->addMonth(),
            'is_online' => false,
            'venue_name' => 'Arena',
            'venue_address' => 'Main St',
            'status' => 'upcoming',
        ]);

        $tier = TicketTier::create([
            'event_id' => $event->id,
            'name' => 'General',
            'base_price' => 100,
            'total_seats' => 10,
            'sold_count' => 2,
            'sale_starts_at' => now()->subDay(),
            'sale_ends_at' => now()->addMonth(),
            'version' => 1,
        ]);

        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        SeatReservation::create([
            'user_id' => $customer->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 3,
            'reserved_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Match')
            ->assertJsonPath('data.tiers.0.remaining_capacity', 5);
    }

    public function test_list_filters_by_category(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer', 'is_active' => true]);
        $catA = Category::create(['name' => 'A']);
        $catB = Category::create(['name' => 'B']);

        Event::create([
            'organizer_id' => $organizer->id,
            'category_id' => $catA->id,
            'name' => 'Only A',
            'description' => 'x',
            'event_datetime' => now()->addMonth(),
            'is_online' => true,
            'status' => 'upcoming',
        ]);

        Event::create([
            'organizer_id' => $organizer->id,
            'category_id' => $catB->id,
            'name' => 'Only B',
            'description' => 'x',
            'event_datetime' => now()->addMonth(),
            'is_online' => true,
            'status' => 'upcoming',
        ]);

        $this->getJson('/api/events?category_id='.$catA->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.name', 'Only A');
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $this->getJson('/api/events/99999')->assertNotFound()
            ->assertJsonPath('success', false);
    }
}
