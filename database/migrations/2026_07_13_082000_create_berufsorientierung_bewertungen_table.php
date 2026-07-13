<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berufsorientierung_bewertungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gruppe_id')->constrained('gruppes')->cascadeOnDelete();
            $table->foreignId('personen_id')->constrained('personens')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kriterium', 191);
            $table->integer('bewertung')->nullable();
            $table->unsignedBigInteger('legacy_bewertungsbogen_id');
            $table->timestamps();

            $table->unique(['gruppe_id', 'personen_id', 'kriterium'], 'bo_bewertung_group_person_criterion_unique');
            $table->index('legacy_bewertungsbogen_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berufsorientierung_bewertungen');
    }
};
