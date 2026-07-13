<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50);
            $table->string('mapping_version', 50);
            $table->string('status', 30)->default('running');
            $table->boolean('dry_run')->default(false);
            $table->string('source_checksum', 64)->nullable();
            $table->unsignedBigInteger('read_count')->default(0);
            $table->unsignedBigInteger('imported_count')->default(0);
            $table->unsignedBigInteger('skipped_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'status']);
        });

        Schema::create('legacy_id_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_import_run_id')->constrained()->cascadeOnDelete();
            $table->string('source', 50);
            $table->string('source_table', 100);
            $table->string('source_id', 191);
            $table->string('target_table', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('record_checksum', 64);
            $table->string('status', 30);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_table', 'source_id', 'target_table'], 'legacy_mapping_source_target_unique');
            $table->index(['target_table', 'target_id']);
        });

        Schema::create('legacy_record_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_import_run_id')->constrained()->cascadeOnDelete();
            $table->string('source', 50);
            $table->string('source_table', 100);
            $table->string('source_id', 191);
            $table->json('payload');
            $table->string('record_checksum', 64);
            $table->string('classification', 30);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_table', 'source_id'], 'legacy_snapshot_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_record_snapshots');
        Schema::dropIfExists('legacy_id_mappings');
        Schema::dropIfExists('legacy_import_runs');
    }
};
