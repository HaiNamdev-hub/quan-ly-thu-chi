<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_sign_in_with_username_and_receive_no_password_data(): void
    {
        $user = User::factory()->create(['username' => 'linh.nguyen', 'password' => 'A-secure-password-123', 'role' => Role::Manager]);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/login', ['login' => 'linh.nguyen', 'password' => 'A-secure-password-123'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'MANAGER')
            ->assertJsonMissingPath('user.password');

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_invalid_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'staff@example.test', 'password' => 'A-secure-password-123']);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/login', ['login' => 'staff@example.test', 'password' => 'incorrect-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('login');
    }

    public function test_staff_cannot_access_user_administration_api(): void
    {
        $staff = User::factory()->create(['role' => Role::Staff]);

        $this->actingAs($staff)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_manager_cannot_change_user_role(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);
        $staff = User::factory()->create(['role' => Role::Staff]);

        $this->actingAs($manager)
            ->putJson('/api/admin/users/'.$staff->id, ['role' => Role::Admin->value])
            ->assertForbidden();
    }

    public function test_admin_can_create_and_lock_an_account_with_audit_entries(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'Nguyễn Minh Anh',
                'username' => 'minh.anh',
                'email' => 'minh.anh@example.test',
                'phone' => '0900123456',
                'role' => Role::Staff->value,
                'password' => 'Another-secure-123',
            ])
            ->assertCreated()
            ->assertJsonPath('user.is_active', true);

        $created = User::query()->where('username', 'minh.anh')->firstOrFail();
        $this->assertTrue(Hash::check('Another-secure-123', $created->password));

        $this->actingAs($admin)->putJson('/api/admin/users/'.$created->id, ['is_active' => false])->assertOk();
        $this->assertFalse($created->fresh()->is_active);
        $this->assertSame(2, AuditLog::query()->where('user_id', $admin->id)->count());
    }

    public function test_locked_user_cannot_continue_using_authenticated_api(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->getJson('/api/me')->assertStatus(423);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/login', ['login' => $user->email, 'password' => 'password'])
            ->assertUnprocessable();
    }
}
