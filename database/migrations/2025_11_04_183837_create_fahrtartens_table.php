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
        Schema::create('fahrtartens', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // z.B. "PKW", "ÖPNV Ticket"
            $table->string('beschreibung')->nullable();
        });


        DB::table('fahrtartens')->insert([
            ['name' => 'Monatskarte', 'beschreibung' => 'ÖPNV-Monatsticket für Bus und Bahn'],
            ['name' => 'Wochenkarte', 'beschreibung' => 'ÖPNV-Wochenticket für Bus und Bahn'],
            ['name' => 'Tageskarte', 'beschreibung' => 'Einzelfahrt oder Tagesticket'],
            ['name' => 'Fahrgemeinschaft', 'beschreibung' => 'Gemeinsame Fahrt mit anderen Teilnehmern'],
            ['name' => 'Sprit', 'beschreibung' => 'Kraftstoffkosten für private Fahrzeugnutzung'],
            ['name' => 'E-Roller', 'beschreibung' => 'Nutzung eines E-Scooters oder E-Rollers'],
            ['name' => 'Fahrrad / E-Bike', 'beschreibung' => 'Nutzung von Fahrrad oder E-Bike für Arbeitsweg'],
            ['name' => 'Privat-PKW', 'beschreibung' => 'Fahrt mit eigenem Auto zum Einsatzort'],
            ['name' => 'Taxi / Mietwagen', 'beschreibung' => 'Einzelfahrt mit Taxi oder Mietfahrzeug'],
            ['name' => 'Deutschlandticket', 'beschreibung' => 'Monatlicher Zuschuss oder Erstattung für 49€-Ticket'],
            ['name' => 'Fair Ticket', 'beschreibung' => 'Vergünstigtes Ticket für Teilnehmende bestimmter Programme'],
            ['name' => 'Fair Ticket Plus', 'beschreibung' => 'Erweitertes Fair Ticket mit zusätzlichen Leistungen'],
            ['name' => 'Sonderfahrt', 'beschreibung' => 'Einzelfall oder Sonderfahrt z. B. zu Vorstellungsgespräch'],
            ['name' => 'Bahncard / Rabattkarte', 'beschreibung' => 'Erstattung oder Zuschuss für Bahncard'],
            ['name' => 'Regionalticket', 'beschreibung' => 'Ticket für Nahverkehr innerhalb einer Region'],
            ['name' => 'Jahreskarte', 'beschreibung' => 'Dauerkarte für Bus und Bahn (12 Monate)'],
            ['name' => 'Einzelfahrt', 'beschreibung' => 'Einzelne Fahrt ohne Abo oder Dauerkarte'],
            ['name' => 'Pendlerpauschale', 'beschreibung' => 'Pauschale für regelmäßige Fahrten ohne Nachweis'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fahrtartens');
    }
};
