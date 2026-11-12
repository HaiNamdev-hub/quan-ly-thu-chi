<?php

namespace Tests\Feature;

use App\Models\User;
use App\Role;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_three_role_accounts_and_does_not_reset_existing_passwords_on_rerun(): void
    {
        $existingAdmin = User::factory()->create([
            'email' => 'admin@caothi.local',
            'role' => Role::Staff,
            'password' => 'Existing-Admin-Pass-2026',
        ]);
        $adminHash = $existingAdmin->password;

        config([
            'seeders.accounts.admin_password' => 'Admin-Unique-Pass-2026',
            'seeders.accounts.manager_password' => 'Manager-Unique-Pass-2026',
            'seeders.accounts.staff_password' => 'Staff-Unique-Pass-2026',
        ]);

        $this->seed(UserSeeder::class);
        $admin = User::query()->where('email', 'admin@caothi.local')->firstOrFail();

        $this->assertSame(Role::Admin, $admin->role);
        $this->assertSame(Role::Manager, User::query()->where('email', 'manager@caothi.local')->firstOrFail()->role);
        $this->assertSame(Role::Staff, User::query()->where('email', 'staff@caothi.local')->firstOrFail()->role);

        config([
            'seeders.accounts.admin_password' => 'Changed-Admin-Pass-2026',
            'seeders.accounts.manager_password' => 'Changed-Manager-Pass-2026',
            'seeders.accounts.staff_password' => 'Changed-Staff-Pass-2026',
        ]);
        $this->seed(UserSeeder::class);

        $this->assertSame(3, User::query()->count());
        $this->assertSame($adminHash, $admin->fresh()->password);
        $this->assertTrue(Hash::check('Existing-Admin-Pass-2026', $admin->fresh()->password));
    }

    public function test_it_requires_separate_passwords_for_missing_accounts(): void
    {
        config([
            'seeders.accounts.admin_password' => 'Same-Password-For-All',
            'seeders.accounts.manager_password' => 'Same-Password-For-All',
            'seeders.accounts.staff_password' => 'Same-Password-For-All',
        ]);

        try {
            $this->seed(UserSeeder::class);
            $this->fail('Seeder phải từ chối mật khẩu dùng chung.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Mỗi tài khoản khởi tạo phải có mật khẩu riêng biệt.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }
}
