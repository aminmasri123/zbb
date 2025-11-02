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
        Schema::create('abschluesses', function (Blueprint $table) {
            $table->id();
            $table->enum('typ', ['schule', 'beruf', 'hochschule', 'weiterbildung']);
            $table->string('bezeichnung');
            $table->text('beschreibung')->nullable();
            $table->timestamps();
        });
    }
    /*
        ############  Hauptschulabschluss  ##############

        ohne Hauptschulabschluss
        Hauptschulabschluss
        mittlere Reife
        Fachhochschulreife
        Hochschulreife
        Berufsfachschule, die zur Hochschulreife/Fachhochschulreife führt
        Fachoberschule 1-jährig (nach vorheriger Berufsausbildung)
        Berufsoberschule/Technische Oberschule

    ############  Berufsabschluss  ##############
        ohne Berufsabschluss
        Berufsvorbereitungsjahr
        berufliche Schulen, die zur mittleren Reife führen
        Berufsgrundbildungsjahr
        Berufsschulen (duales System)
        Berufsfachschulen, die einen Berufsabschluss vermittelt (o. Gesundheits-Sozialberufe, Erzieherausbildung)
        Einjährige Programme an Ausbildungsstätten/Schulen für Gesundheits-/Sozialberuf
        zwei-/dreijährige Programme an Ausbildungsstätten/Schulen für Gesundheits-/Sozialberufe
        Berufsschule (duales System, Zweitausbildung nach Erwerb einer Studienberechtigung)
        Berufsfachschule, die einen Berufsabschluss vermittelt (Zweitausbildung nach Erwerb einer Studienberechtigung)
        berufliche Programme, die sowohl einen Berufsabschluss wie auch eine Studienberechtigung vermittelt
        Fachschulen (o. Gesundheits-/Sozialberufe, Erzieherausbildung) einschl. Meisterausbildung, Technikerausbildung
        Ausbildungsstätten/Schulen für Erzieher/-innen
        Bachelor an Universitäten, Fachhochschulen, Verwaltungsfachhochschulen, Berufsakademien
        zweiter Bachelorstudiengang
        Diplom (FH)-Studiengang
        Diplomstudiengang (FH) einer Verwaltungsfachhochschule
        zweiter Diplom (FH)-Studiengang
        Masterstudiengang an Universitäten, Fachhochschulen, Verwaltungshochschulen, Berufsakademien
        zweiter Masterstudiengang
        Diplom-Studiengang
        zweiter Diplom-Studiengang
        Hochschulabschluss
        Promotionsstudium



    */
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abschluesses');
    }
};
