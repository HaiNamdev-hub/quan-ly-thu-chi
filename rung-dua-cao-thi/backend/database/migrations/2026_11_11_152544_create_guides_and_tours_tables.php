<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('phone', 30)->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tour_counters', function (Blueprint $table): void {
            $table->date('tour_date')->primary();
            $table->unsignedSmallInteger('last_sequence')->default(0);
            $table->timestamps();
        });

        Schema::create('photo_price_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('photo_type', 40);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->bigInteger('vietnamese_price');
            $table->bigInteger('international_price');
            $table->bigInteger('unit_cost');
            $table->boolean('needs_reconciliation')->default(true);
            $table->timestamps();
            $table->unique(
                ['photo_type', 'effective_from'],
                'uq_photo_type_date'
            );

            $table->index(
                ['photo_type', 'effective_from', 'effective_to'],
                'idx_photo_price_period'
            );
        });

        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('rule_key', 50);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('guest_threshold')->nullable();
            $table->bigInteger('amount');
            $table->boolean('needs_reconciliation')->default(true);
            $table->timestamps();
            $table->unique(['rule_key', 'effective_from']);
        });

        Schema::create('ticket_price_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_type', 20);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->bigInteger('ticket_price');
            $table->bigInteger('ticket_cost');
            $table->boolean('needs_reconciliation')->default(true);
            $table->timestamps();
            $table->unique(['customer_type', 'effective_from']);
        });

        Schema::create('tour_groups', function (Blueprint $table): void {
            $table->id();
            $table->date('tour_date')->index();
            $table->unsignedSmallInteger('sequence');
            $table->string('code', 30);
            $table->foreignId('guide_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('vietnamese_guests')->default(0);
            $table->unsignedSmallInteger('international_guests')->default(0);
            $table->unsignedSmallInteger('total_guests')->default(0);
            $table->unsignedSmallInteger('paid_tickets')->default(0);
            $table->unsignedSmallInteger('boats')->default(0);
            $table->bigInteger('ticket_vietnamese_unit_price')->default(0);
            $table->bigInteger('ticket_international_unit_price')->default(0);
            $table->bigInteger('ticket_cost_unit_price')->default(0);
            $table->bigInteger('boat_cost_unit_price')->default(0);
            $table->bigInteger('ticket_revenue')->default(0);
            $table->bigInteger('ticket_cost')->default(0);
            $table->bigInteger('boat_cost')->default(0);
            $table->bigInteger('door_commission')->default(0);
            $table->unsignedSmallInteger('coconut_portions')->default(0);
            $table->unsignedSmallInteger('ao_dai_units')->default(0);
            $table->bigInteger('coconut_commission_unit_price')->default(0);
            $table->bigInteger('ao_dai_commission_unit_price')->default(0);
            $table->bigInteger('ao_dai_revenue')->default(0);
            $table->bigInteger('photo_revenue')->default(0);
            $table->bigInteger('photo_cost')->default(0);
            $table->bigInteger('photo_commission')->default(0);
            $table->bigInteger('other_expenses')->default(0);
            $table->string('ticket_payment_method', 20)->nullable();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->text('notes')->nullable();
            $table->text('unlock_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tour_date', 'sequence']);
            $table->unique(['tour_date', 'code']);
            $table->index(['tour_date', 'status']);
            $table->index(['guide_id', 'tour_date']);
            $table->index(['created_by', 'tour_date']);
        });

        Schema::create('tour_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_group_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 40);
            $table->unsignedSmallInteger('quantity')->default(0);
            $table->bigInteger('revenue')->default(0);
            $table->bigInteger('commission_unit_price')->default(0);
            $table->bigInteger('commission_amount')->default(0);
            $table->timestamps();
            $table->unique(['tour_group_id', 'service_type']);
        });

        Schema::create('tour_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_group_id')->constrained()->cascadeOnDelete();
            $table->string('photo_type', 40);
            $table->unsignedSmallInteger('quantity')->default(0);
            $table->bigInteger('unit_price_snapshot')->default(0);
            $table->bigInteger('unit_cost_snapshot')->default(0);
            $table->bigInteger('revenue')->default(0);
            $table->bigInteger('cost')->default(0);
            $table->bigInteger('commission')->default(0);
            $table->timestamps();
            $table->unique(['tour_group_id', 'photo_type']);
        });
    }

    public function down(): void
{
    Schema::dropIfExists('tour_photos');
    Schema::dropIfExists('tour_services');
    Schema::dropIfExists('tour_groups');
    Schema::dropIfExists('ticket_price_histories');
    Schema::dropIfExists('commission_rules');
    Schema::dropIfExists('photo_price_histories');
    Schema::dropIfExists('tour_counters');
    Schema::dropIfExists('guides');
}
};
