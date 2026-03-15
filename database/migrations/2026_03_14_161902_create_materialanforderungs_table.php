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
        Schema::create('materialanforderungs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekts')->onDelete('cascade');
            $table->string('kostenstelle');
            $table->enum('status', ['entwurf', 'eingereicht', 'sachlich_genehmigt', 'kaufmaennisch_genehmigt','bestellt', 'teilweise_geliefert', 'geliefert', 'bestaetigt', 'abgeschlossen', 'abgelehnt', 'zur_ueberarbeitung', 'storniert'])->default('entwurf');
            $table->decimal('gesamtpreis', 10, 2)->default(0); // Summe aller Positionen
            $table->decimal('endsumme', 10, 2)->default(0); // Summe inkl. MwSt
            $table->text('bemerkungen')->nullable();
            $table->foreignId('ersteller_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materialanforderungs');
    }
};
