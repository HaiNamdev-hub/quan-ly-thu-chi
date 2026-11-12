<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('role', 20)->default('STAFF')->index()->after('password');
            $table->boolean('is_active')->default(true)->index()->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['username', 'phone', 'role', 'is_active', 'last_login_at']);
        });
    }
};
