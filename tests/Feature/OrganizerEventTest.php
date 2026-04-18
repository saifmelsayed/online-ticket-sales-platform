<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizerEventTest extends TestCase
{
    use RefreshDatabase;

    private function approvedOrganizer(): User
    {
        $user = User::factory()->create([
            'role' => 'organizer',
            'credit_balance' => 0,
            'is_active' => true,
        ]);
        Organizer::create([
            'user_id' => $user->id,
            'approval_status' => 'approved',
        ]);

        return $user;
    }

    public function test_pending_organizer_cannot_create_event(): void
    {
        $user = User::factory()->create([
            'role' => 'organizer',
            'credit_balance' => 0,
            'is_active' => true,
        ]);
        Organizer::create([
            'user_id' => $user->id,
            'approval_status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $category = Category::create(['name' => 'Concert']);

        $this->postJson('/api/organizer/events', [
            'category_id' => $category->id,
            'name' => 'Show',
            'description' => 'Desc',
            'event_datetime' => now()->addWeek()->toDateTimeString(),
            'is_online' => false,
            'venue_name' => 'Hall',
            'venue_address' => 'Street 1',
        ])->assertForbidden();
    }

    public function test_organizer_creates_event_and_lists_it(): void
    {
        $user = $this->approvedOrganizer();
        Sanctum::actingAs($user);
        $category = Category::create(['name' => 'Sports']);

        $this->postJson('/api/organizer/events', [
            'category_id' => $category->id,
            'name' => 'Final Match',
            'description' => 'Big game',
            'event_datetime' => now()->addWeek()->toDateTimeString(),
            'is_online' => false,
            'venue_name' => 'Stadium',
            'venue_address' => 'City',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Final Match');

        $this->getJson('/api/organizer/events')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_organizer_adds_tier(): void
    {
        $user = $this->approvedOrganizer();
        Sanctum::actingAs($user);
        $category = Category::create(['name' => 'Theater']);

        $event = Event::create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Play',
            'description' => 'Drama',
            'event_datetime' => now()->addWeek(),
            'venue_name' => 'Hall',
            'venue_address' => 'Ave',
            'is_online' => false,
            'status' => 'upcoming',
        ]);

        $starts = now()->addDay();
        $ends = now()->addDays(7);

        $this->postJson("/api/organizer/events/{$event->id}/tiers", [
            'name' => 'Standard',
            'base_price' => 100,
            'total_seats' => 50,
            'sale_starts_at' => $starts->toDateTimeString(),
            'sale_ends_at' => $ends->toDateTimeString(),
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Standard');

        $this->assertDatabaseHas('ticket_tiers', [
            'event_id' => $event->id,
            'name' => 'Standard',
            'total_seats' => 50,
        ]);
    }

    public function test_organizer_updates_tier(): void
    {
        $user = $this->approvedOrganizer();
        Sanctum::actingAs($user);
        $category = Category::create(['name' => 'Music']);

        $event = Event::create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Gig',
            'description' => 'Live',
            'event_datetime' => now()->addWeek(),
            'venue_name' => 'Hall',
            'venue_address' => 'St',
            'is_online' => false,
            'status' => 'upcoming',
        ]);

        $tier = TicketTier::create([
            'event_id' => $event->id,
            'name' => 'GA',
            'base_price' => 50,
            'total_seats' => 100,
            'sale_starts_at' => now()->addDay(),
            'sale_ends_at' => now()->addDays(5),
            'sold_count' => 0,
            'version' => 1,
        ]);

        $this->putJson("/api/organizer/events/{$event->id}/tiers/{$tier->id}", [
            'name' => 'General Admission',
            'base_price' => 55,
        ])->assertOk()
            ->assertJsonPath('data.name', 'General Admission')
            ->assertJsonPath('data.base_price', 55);

        $this->assertSame('General Admission', $tier->fresh()->name);
    }

    public function test_organizer_cannot_shrink_total_seats_below_sold_plus_holds(): void
    {
        $user = $this->approvedOrganizer();
        Sanctum::actingAs($user);
        $category = Category::create(['name' => 'Z']);

        $event = Event::create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'E',
            'description' => 'D',
            'event_datetime' => now()->addWeek(),
            'is_online' => true,
            'status' => 'upcoming',
        ]);

        $tier = TicketTier::create([
            'event_id' => $event->id,
            'name' => 'T',
            'base_price' => 10,
            'total_seats' => 50,
            'sale_starts_at' => now()->subHour(),
            'sale_ends_at' => now()->addDays(5),
            'sold_count' => 10,
            'version' => 1,
        ]);

        $this->putJson("/api/organizer/events/{$event->id}/tiers/{$tier->id}", [
            'total_seats' => 5,
        ])->assertUnprocessable();
    }

    public function test_organizer_deletes_unused_tier(): void
    {
        $user = $this->approvedOrganizer();
        Sanctum::actingAs($user);
        $category = Category::create(['name' => 'Q']);

        $event = Event::create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'E2',
            'description' => 'D',
            'event_datetime' => now()->addWeek(),
            'is_online' => true,
            'status' => 'upcoming',
        ]);

        $tier = TicketTier::create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'base_price' => 200,
            'total_seats' => 20,
            'sale_starts_at' => now()->addDay(),
            'sale_ends_at' => now()->addDays(5),
            'sold_count' => 0,
            'version' => 1,
        ]);

        $this->deleteJson("/api/organizer/events/{$event->id}/tiers/{$tier->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('ticket_tiers', ['id' => $tier->id]);
    }

    public function test_organizer_cannot_delete_tier_with_sales(): void
    {
        $user = $this->approvedOrganizer();
        Sanctum::actingAs($user);
        $category = Category::create(['name' => 'R']);

        $event = Event::create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'E3',
            'description' => 'D',
            'event_datetime' => now()->addWeek(),
            'is_online' => true,
            'status' => 'upcoming',
        ]);

        $tier = TicketTier::create([
            'event_id' => $event->id,
            'name' => 'Sold',
            'base_price' => 20,
            'total_seats' => 20,
            'sale_starts_at' => now()->subHour(),
            'sale_ends_at' => now()->addDays(5),
            'sold_count' => 3,
            'version' => 1,
        ]);

        $this->deleteJson("/api/organizer/events/{$event->id}/tiers/{$tier->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('ticket_tiers', ['id' => $tier->id]);
    }

    public function test_organizer_updates_event(): void
    {
        $user = $this->approvedOrganizer();
        Sanctum::actingAs($user);
        $category = Category::create(['name' => 'Conf']);

        $event = Event::create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Old',
            'description' => 'Old desc',
            'event_datetime' => now()->addWeek(),
            'venue_name' => 'V',
            'venue_address' => 'A',
            'is_online' => false,
            'status' => 'upcoming',
        ]);

        $this->putJson("/api/organizer/events/{$event->id}", [
            'name' => 'New Title',
        ])->assertOk()
            ->assertJsonPath('data.name', 'New Title');
    }

    public function test_organizer_cannot_update_other_organizer_event(): void
    {
        $userA = $this->approvedOrganizer();
        $userB = $this->approvedOrganizer();
        Sanctum::actingAs($userA);
        $category = Category::create(['name' => 'X']);

        $event = Event::create([
            'organizer_id' => $userB->id,
            'category_id' => $category->id,
            'name' => 'B only',
            'description' => 'D',
            'event_datetime' => now()->addWeek(),
            'is_online' => true,
            'status' => 'upcoming',
        ]);

        $this->putJson("/api/organizer/events/{$event->id}", [
            'name' => 'Hack',
        ])->assertNotFound();
    }

    public function test_organizer_cancels_own_event(): void
    {
        $user = $this->approvedOrganizer();
        Sanctum::actingAs($user);
        $category = Category::create(['name' => 'Y']);

        $event = Event::create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Cancel me',
            'description' => 'D',
            'event_datetime' => now()->addWeek(),
            'is_online' => true,
            'status' => 'upcoming',
        ]);

        $this->patchJson("/api/organizer/events/{$event->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $event->fresh()->status);
    }
}
