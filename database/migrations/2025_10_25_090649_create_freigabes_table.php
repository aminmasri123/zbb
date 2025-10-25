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
        Schema::create('freigabes', function (Blueprint $table) {
            $table->id();

                // 🔸 1. Polymorph: Was wird geteilt (z. B. Letter, Aktennotiz)
                $table->morphs('shareable_from');
                // erstellt: shareable_from_id + shareable_from_type

                // 🔸 2. Polymorph: Mit wem wird geteilt (z. B. User, Project)
                $table->morphs('shareable_to');
                // erstellt: shareable_to_id + shareable_to_type

                // 🔸 Zusatzinfos
                $table->enum('right', ['lesen', 'bearbeiten'])->default('lesen');
                $table->foreignId('shared_by')->constrained('users')->onDelete('cascade');

                $table->timestamp('shared_at')->useCurrent();
                $table->timestamps();

                // Optional: Performance-Indizes
                $table->index(['shareable_from_id', 'shareable_from_type']);
                $table->index(['shareable_to_id', 'shareable_to_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freigabes');
    }
};
