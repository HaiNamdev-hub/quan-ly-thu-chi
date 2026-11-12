<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excel_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('source_name');
            $table->char('source_sha256', 64)->unique();
            $table->string('storage_path');
            $table->string('status', 30)->default('uploaded')->index();
            $table->string('source_sheet', 80)->default('NHAT KY DOAN');
            $table->json('summary')->nullable();
            $table->foreignId('imported_by')->constrained('users');
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('excel_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('excel_import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('source_sheet', 80);
            $table->unsignedInteger('source_row');
            $table->char('row_sha256', 64);
            $table->string('status', 30)->default('pending')->index();
            $table->json('source_values');
            $table->json('mapped_values')->nullable();
            $table->json('issues')->nullable();
            $table->foreignId('tour_group_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['excel_import_batch_id', 'source_sheet', 'source_row'], 'uq_excel_import_source_row');
            $table->index(['source_sheet', 'row_sha256']);
        });

        Schema::create('excel_import_archives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('excel_import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('source_sheet', 80);
            $table->unsignedInteger('source_row');
            $table->string('record_type', 50);
            $table->json('source_values');
            $table->string('status', 30)->default('archived');
            $table->timestamps();
            $table->unique(['excel_import_batch_id', 'source_sheet', 'source_row'], 'uq_excel_archive_source_row');
        });

        Schema::table('tour_groups', function (Blueprint $table): void {
            $table->string('source_type', 20)->default('manual')->index();
            $table->boolean('is_reconciled')->default(true)->index();
            $table->boolean('counts_in_dashboard')->default(true)->index();
            $table->foreignId('excel_import_row_id')->nullable()->unique()->constrained('excel_import_rows')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tour_groups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('excel_import_row_id');
            $table->dropColumn(['source_type', 'is_reconciled', 'counts_in_dashboard']);
        });

        Schema::dropIfExists('excel_import_archives');
        Schema::dropIfExists('excel_import_rows');
        Schema::dropIfExists('excel_import_batches');
    }
};
