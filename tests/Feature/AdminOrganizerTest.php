<?php

namespace Tests\Feature;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrganizerTest extends TestCase
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

    public function test_non_admin_cannot_access_pending_organizers(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        Sanctum::actingAs($customer);

        $this->getJson('/api/admin/pending-organizers')
            ->assertForbidden()
            ->assertJson(['success' => false]);
    }

    public function test_admin_lists_pending_organizers(): void
    {
        $organizerUser = User::factory()->create([
            'role' => 'organizer',
            'credit_balance' => 0,
            'is_active' => true,
        ]);
        Organizer::create([
            'user_id' => $organizerUser->id,
            'approval_status' => 'pending',
        ]);

        Sanctum::actingAs($this->actingAdmin());

        $response = $this->getJson('/api/admin/pending-organizers');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_approves_organizer(): void
    {
        $organizerUser = User::factory()->create([
            'role' => 'organizer',
            'credit_balance' => 0,
            'is_active' => true,
        ]);
        $organizer = Organizer::create([
            'user_id' => $organizerUser->id,
            'approval_status' => 'pending',
        ]);

        Sanctum::actingAs($this->actingAdmin());

        $this->patchJson("/api/admin/approve/{$organizer->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('approved', $organizer->fresh()->approval_status);
    }

    public function test_admin_rejects_organizer(): void
    {
        $organizerUser = User::factory()->create([
            'role' => 'organizer',
            'credit_balance' => 0,
            'is_active' => true,
        ]);
        $organizer = Organizer::create([
            'user_id' => $organizerUser->id,
            'approval_status' => 'pending',
        ]);

        Sanctum::actingAs($this->actingAdmin());

        $this->patchJson("/api/admin/reject/{$organizer->id}")
            ->assertOk();

        $this->assertSame('rejected', $organizer->fresh()->approval_status);
    }

    public function test_admin_lists_approved_organizers(): void
    {
        $organizerUser = User::factory()->create([
            'role' => 'organizer',
            'credit_balance' => 0,
            'is_active' => true,
        ]);
        Organizer::create([
            'user_id' => $organizerUser->id,
            'approval_status' => 'approved',
        ]);

        Sanctum::actingAs($this->actingAdmin());

        $this->getJson('/api/admin/approved-organizers')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_deactivates_customer(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->actingAdmin());

        $this->patchJson("/api/admin/deactivate/{$customer->id}")
            ->assertOk();

        $this->assertFalse((bool) $customer->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = $this->actingAdmin();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/deactivate/{$admin->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_admin_cannot_deactivate_other_admin(): void
    {
        $admin1 = $this->actingAdmin();
        $admin2 = User::factory()->create([
            'role' => 'admin',
            'credit_balance' => 0,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin1);

        $this->patchJson("/api/admin/deactivate/{$admin2->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot deactivate an admin account.');
    }

    public function test_admin_reactivates_user(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->actingAdmin());

        $this->patchJson("/api/admin/reactivate/{$customer->id}")
            ->assertOk();

        $this->assertTrue((bool) $customer->fresh()->is_active);
    }
}
