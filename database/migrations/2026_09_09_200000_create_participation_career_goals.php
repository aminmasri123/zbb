<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('participation_career_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_person_id')->constrained('projekt_has_personens')->restrictOnDelete();
            $table->string('target', 30);
            $table->string('occupation')->nullable();
            $table->text('alternatives')->nullable();
            $table->text('notes')->nullable();
            $table->string('agreement_status', 30);
            $table->date('documented_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name');
            $table->timestamps();
            $table->index(['project_person_id', 'documented_on'], 'career_goal_participation_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_career_goals');
    }
};
