<?php

namespace Database\Seeders;

use App\Models\User;
use App\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /** @var array<string, array{name: string, username: string, email: string, role: Role, password_config: string}> */
    private const ACCOUNTS = [
        'admin' => ['name' => 'Quản trị viên Cao Thị', 'username' => 'admin', 'email' => 'admin@caothi.local', 'role' => Role::Admin, 'password_config' => 'seeders.accounts.admin_password'],
        'manager' => ['name' => 'Quản lý Cao Thị', 'username' => 'manager', 'email' => 'manager@caothi.local', 'role' => Role::Manager, 'password_config' => 'seeders.accounts.manager_password'],
        'staff' => ['name' => 'Nhân viên Cao Thị', 'username' => 'staff', 'email' => 'staff@caothi.local', 'role' => Role::Staff, 'password_config' => 'seeders.accounts.staff_password'],
    ];

    public function run(): void
    {
        $missingAccounts = [];
        $passwords = [];
        $existingPasswordHashes = [];
        $existingRoleUpdates = [];

        foreach (self::ACCOUNTS as $key => $account) {
            $existingUser = User::query()->where('email', $account['email'])->first();
            if ($existingUser !== null) {
                $existingPasswordHashes[] = $existingUser->password;
                if ($existingUser->role !== $account['role']) {
                    $existingRoleUpdates[] = [$existingUser, $account['role']];
                }

                continue;
            }

            $password = config($account['password_config']);
            if (! is_string($password) || mb_strlen($password) < 12) {
                throw new RuntimeException(sprintf('Thiếu mật khẩu riêng tối thiểu 12 ký tự cho tài khoản %s qua biến môi trường.', strtoupper($key)));
            }

            if (User::query()->where('username', $account['username'])->exists()) {
                throw new RuntimeException(sprintf('Username %s đã được tài khoản khác sử dụng; seeder không thay đổi tài khoản hiện có.', $account['username']));
            }

            $passwords[$key] = $password;
            $missingAccounts[$key] = $account;
        }

        if (count(array_unique(array_values($passwords), SORT_STRING)) !== count($passwords)) {
            throw new RuntimeException('Mỗi tài khoản khởi tạo phải có mật khẩu riêng biệt.');
        }

        foreach ($passwords as $password) {
            foreach ($existingPasswordHashes as $existingPasswordHash) {
                if (Hash::check($password, $existingPasswordHash)) {
                    throw new RuntimeException('Mỗi tài khoản khởi tạo phải có mật khẩu riêng biệt.');
                }
            }
        }

        DB::transaction(function () use ($existingRoleUpdates, $missingAccounts, $passwords): void {
            foreach ($existingRoleUpdates as [$user, $role]) {
                $user->forceFill(['role' => $role])->save();
            }

            foreach ($missingAccounts as $key => $account) {
                User::query()->create([
                    'name' => $account['name'],
                    'username' => $account['username'],
                    'email' => $account['email'],
                    'password' => $passwords[$key],
                    'role' => $account['role'],
                    'is_active' => true,
                ]);
            }
        });
    }
}
