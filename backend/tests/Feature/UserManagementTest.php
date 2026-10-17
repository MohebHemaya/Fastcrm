<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'مدير النظام',
            'email' => 'admin@fastcrm.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
    }

    public function test_authenticated_user_can_list_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'is_active', 'created_at'],
                ],
                'meta' => ['current_page', 'total', 'per_page', 'last_page'],
            ]);
    }

    public function test_can_search_and_filter_users(): void
    {
        User::factory()->create(['name' => 'محمد أحمد', 'email' => 'mohamed@test.com', 'is_active' => true]);
        User::factory()->create(['name' => 'علي حسن', 'email' => 'ali@test.com', 'is_active' => false]);

        // Search by name
        $resName = $this->actingAs($this->admin)
            ->getJson('/api/v1/users?search=محمد');
        $resName->assertOk();
        $this->assertCount(1, $resName->json('data'));
        $this->assertEquals('محمد أحمد', $resName->json('data.0.name'));

        // Filter by inactive
        $resInactive = $this->actingAs($this->admin)
            ->getJson('/api/v1/users?is_active=false');
        $resInactive->assertOk();
        $this->assertCount(1, $resInactive->json('data'));
        $this->assertEquals('ali@test.com', $resInactive->json('data.0.email'));
    }

    public function test_can_create_user_with_audit_log(): void
    {
        $payload = [
            'name' => 'مستخدم جديد',
            'email' => 'newuser@fastcrm.local',
            'password' => 'Secret12345!',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/users', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'مستخدم جديد')
            ->assertJsonPath('data.email', 'newuser@fastcrm.local');

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@fastcrm.local',
            'name' => 'مستخدم جديد',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user_created',
        ]);
    }

    public function test_validates_required_fields_and_unique_email_on_create(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/users', [
                'name' => '',
                'email' => 'admin@fastcrm.local', // duplicate
                'password' => '123', // too short
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_can_update_user(): void
    {
        $target = User::factory()->create([
            'name' => 'الاسم القديم',
            'email' => 'old@fastcrm.local',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/users/{$target->id}", [
                'name' => 'الاسم الجديد',
                'email' => 'new@fastcrm.local',
                'password' => 'NewPassword123!',
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'الاسم الجديد')
            ->assertJsonPath('data.email', 'new@fastcrm.local')
            ->assertJsonPath('data.is_active', false);

        $target->refresh();
        $this->assertEquals('الاسم الجديد', $target->name);
        $this->assertTrue(Hash::check('NewPassword123!', $target->password));
        $this->assertFalse($target->is_active);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user_updated',
        ]);
    }

    public function test_user_cannot_deactivate_themselves_via_update(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/users/{$this->admin->id}", [
                'name' => 'تحديث اسمي',
                'email' => $this->admin->email,
                'is_active' => false,
            ]);

        $response->assertStatus(422);
        $this->admin->refresh();
        $this->assertTrue($this->admin->is_active);
    }

    public function test_can_toggle_active_status_for_other_user(): void
    {
        $target = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$target->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $target->refresh();
        $this->assertFalse($target->is_active);

        // Toggle back to active
        $response2 = $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$target->id}/toggle-active");

        $response2->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_user_cannot_toggle_active_on_themselves(): void
    {
        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$this->admin->id}/toggle-active");

        $response->assertStatus(422);
    }

    public function test_can_delete_other_user(): void
    {
        $target = User::factory()->create(['name' => 'للحذف', 'email' => 'delete@me.com']);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/users/{$target->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user_deleted',
        ]);
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/users/{$this->admin->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_cannot_delete_last_active_user(): void
    {
        // Admin is the only user in system right now
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/users/{$this->admin->id}");

        $response->assertStatus(422);
    }
}
