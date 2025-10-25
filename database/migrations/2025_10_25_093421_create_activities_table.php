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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            // 🔸 1. Bezug auf das Objekt (z. B. Letter, Project, Aktennotiz)
            $table->morphs('model');

            // 🔸 2. Bezug auf den Benutzer (wer hat’s gemacht)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // 🔸 3. Aktionstyp (z. B. erstellt, bearbeitet, gelöscht)
            $table->enum('action', ['created', 'updated', 'deleted'])
                ->default('created');

            // 🔸 4. Was wurde geändert? bin noch am überlegen
            $table->json('changes')->nullable();

            // 🔸 5. Zeitstempel
            $table->timestamps();

            // 🔸 Optional: Index für Performance
            $table->index(['model_id', 'model_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
