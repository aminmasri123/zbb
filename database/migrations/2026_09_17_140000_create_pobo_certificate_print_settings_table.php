<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pobo_certificate_print_settings')) {
            return;
        }

        Schema::create('pobo_certificate_print_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('horizontal_offset_mm', 6, 2)->default(0);
            $table->decimal('vertical_offset_mm', 6, 2)->default(0);
            $table->decimal('row_spacing_offset_mm', 5, 2)->default(0);
            $table->decimal('cross_font_size_pt', 5, 2)->default(13);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pobo_certificate_print_settings');
    }
};
