<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_door_room_requirements', function (Blueprint $table) {
            $table->foreignId('access_door_id')->constrained('access_doors')->cascadeOnDelete();
            $table->foreignId('raum_id')->constrained('raeumes')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['access_door_id', 'raum_id'], 'access_door_room_requirement_primary');
            $table->index('raum_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_door_room_requirements');
    }
};
