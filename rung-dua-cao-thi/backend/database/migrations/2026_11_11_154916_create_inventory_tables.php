<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->string('sku', 60)->unique();
            $table->string('name', 150);
            $table->string('unit', 30)->default('cái');
            $table->integer('opening_quantity')->default(0);
            $table->integer('current_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->boolean('stock_control_enabled')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('transaction_type', 30);
            $table->integer('quantity_delta');
            $table->foreignId('tour_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['inventory_item_id', 'created_at']);
            $table->index(['tour_group_id', 'transaction_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_items');
    }
};
