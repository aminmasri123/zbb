<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partners', fn (Blueprint $table) => $table->string('schulnummer', 10)->nullable());
        Schema::create('bop_school_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekts')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('personen_ist_schuelers')->cascadeOnDelete();
            $table->json('statuses');
            $table->timestamps();
            $table->unique(['projekt_id', 'student_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('bop_school_attendance');
        Schema::table('partners', fn (Blueprint $table) => $table->dropColumn('schulnummer'));
    }
};
