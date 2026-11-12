<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('rule_key', 50);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('value');
            $table->boolean('needs_reconciliation')->default(false);
            $table->timestamps();
            $table->unique(['rule_key', 'effective_from']);
            $table->index(['rule_key', 'effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_rules');
    }
};
