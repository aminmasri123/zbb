<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_floor_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standort_id')->constrained('standorts')->cascadeOnDelete();
            $table->string('floor_label', 80);
            $table->string('name', 160);
            $table->string('image_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['standort_id', 'floor_label']);
            $table->index(['standort_id', 'active']);
        });

        Schema::create('access_floor_plan_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_floor_plan_id')->constrained('access_floor_plans')->cascadeOnDelete();
            $table->foreignId('raum_id')->constrained('raeumes')->cascadeOnDelete();
            $table->decimal('x_percent', 7, 4);
            $table->decimal('y_percent', 7, 4);
            $table->decimal('width_percent', 7, 4);
            $table->decimal('height_percent', 7, 4);
            $table->timestamps();

            $table->unique(['access_floor_plan_id', 'raum_id'], 'access_floor_plan_room_unique');
        });

        Schema::create('access_floor_plan_doors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_floor_plan_id')->constrained('access_floor_plans')->cascadeOnDelete();
            $table->foreignId('access_door_id')->constrained('access_doors')->cascadeOnDelete();
            $table->decimal('x_percent', 7, 4);
            $table->decimal('y_percent', 7, 4);
            $table->decimal('rotation_degrees', 7, 2)->default(0);
            $table->timestamps();

            $table->unique(['access_floor_plan_id', 'access_door_id'], 'access_floor_plan_door_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_floor_plan_doors');
        Schema::dropIfExists('access_floor_plan_rooms');
        Schema::dropIfExists('access_floor_plans');
    }
};
