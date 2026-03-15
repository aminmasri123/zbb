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
        Schema::create('materialanforderung_genehmigungs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anforderung_id')->constrained('materialanforderungs')->onDelete('cascade');
            $table->foreignId('genehmiger_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['in Bearbeitung', 'Genehmigt', 'Abgelehnt'])->default('Offen');
            $table->text('kommentar')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materialanforderung_genehmigungs');
    }
};
