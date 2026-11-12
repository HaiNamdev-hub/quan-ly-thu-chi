<?php

namespace Database\Seeders;

use App\Models\User;
use App\Role;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = config('admin.name');
        $username = config('admin.username');
        $email = config('admin.email');
        $password = config('admin.password');

        if (! $name || ! $username || ! $email || ! $password || mb_strlen($password) < 12) {
            throw new RuntimeException('Cần cấu hình ADMIN_NAME, ADMIN_USERNAME, ADMIN_EMAIL và ADMIN_PASSWORD (tối thiểu 12 ký tự) trước khi tạo admin.');
        }

        if (User::query()->where('email', $email)->orWhere('username', $username)->exists()) {
            throw new RuntimeException('Username hoặc email đã tồn tại. Seeder không ghi đè tài khoản hiện có.');
        }

        User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'role' => Role::Admin,
            'is_active' => true,
        ]);
    }
}
