<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('raum_has_personens')) {
            return;
        }

        Schema::create('raum_has_personens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_has_personen_id')
                ->constrained('projekt_has_personens')
                ->cascadeOnDelete();
            $table->foreignId('raum_id')
                ->constrained('raeumes')
                ->cascadeOnDelete();
            $table->string('assignment_type', 30)->default('arbeitsbereich');
            $table->boolean('is_default')->default(false);
            $table->string('bemerkung')->nullable();
            $table->timestamps();

            $table->unique(
                ['projekt_has_personen_id', 'raum_id', 'assignment_type'],
                'raum_person_assignment_unique'
            );
            $table->index(
                ['projekt_has_personen_id', 'assignment_type'],
                'raum_person_assignment_type_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raum_has_personens');
    }
};
