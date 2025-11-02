<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Standort;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use App\Models\ProjektHasPersonen;
use Illuminate\Support\Facades\DB;
use App\Models\StandortHasPersonen;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        DB::table('leistungsbezueges')->insert([
            ['bezeichnung' => 'kein Leistungsbezug'],
            ['bezeichnung' => 'Arbeitslosengeld I (SGB III)'],
            ['bezeichnung' => 'Arbeitslosengeld II (SGB II)'],
            ['bezeichnung' => 'Sozialhilfe (SGB XII)'],
            ['bezeichnung' => 'Leistungen nach dem Asylbewerberleistungsgesetz'],
            ['bezeichnung' => 'Kinderzuschlag / Wohngeld'],
            ['bezeichnung' => 'Übergangsgeld (SGB IX)'],
            ['bezeichnung' => 'Rentenleistungen'],
            ['bezeichnung' => 'Sonstige Leistungen'],
        ]);

        DB::table('austritttypens')->insert([
            ['name' => 'Kein Abbruch'],
            ['name' => 'Aufnahme sozialversicherungspflichtiger Beschäftigung'],
            ['name' => 'Aufnahme geringfügige Beschäftigung'],
            ['name' => 'Ausbildungsaufnahme'],
            ['name' => 'weiterer Schulbesuch'],
            ['name' => 'Aufnahme Studium'],
            ['name' => 'Selbstständigkeit'],
            ['name' => 'Kündigung Ausbildungsvertrag'],
            ['name' => 'vorgezogene Abschlussprüfung'],
            ['name' => 'sonstiger Abbruchgrund'],
        ]);


        DB::table('abschluesses')->insert([

            // 🏫 Schulabschlüsse
            ['typ' => 'schule', 'bezeichnung' => 'ohne Hauptschulabschluss'],
            ['typ' => 'schule', 'bezeichnung' => 'Hauptschulabschluss'],
            ['typ' => 'schule', 'bezeichnung' => 'mittlere Reife'],
            ['typ' => 'schule', 'bezeichnung' => 'Fachhochschulreife'],
            ['typ' => 'schule', 'bezeichnung' => 'Hochschulreife'],
            ['typ' => 'schule', 'bezeichnung' => 'Berufsfachschule, die zur Hochschulreife/Fachhochschulreife führt'],
            ['typ' => 'schule', 'bezeichnung' => 'Fachoberschule 1-jährig (nach vorheriger Berufsausbildung)'],
            ['typ' => 'schule', 'bezeichnung' => 'Berufsoberschule/Technische Oberschule'],

            // ⚙️ Berufs- & Studienabschlüsse
            ['typ' => 'beruf', 'bezeichnung' => 'ohne Berufsabschluss'],
            ['typ' => 'beruf', 'bezeichnung' => 'Berufsvorbereitungsjahr'],
            ['typ' => 'beruf', 'bezeichnung' => 'berufliche Schulen, die zur mittleren Reife führen'],
            ['typ' => 'beruf', 'bezeichnung' => 'Berufsgrundbildungsjahr'],
            ['typ' => 'beruf', 'bezeichnung' => 'Berufsfachschule (duales System)'],
            ['typ' => 'beruf', 'bezeichnung' => 'Berufsfachschulen, die einen Berufsabschluss vermitteln (o. Gesundheits-/Sozialberufe, Erzieherausbildung)'],
            ['typ' => 'beruf', 'bezeichnung' => 'Einjährige Programme an Ausbildungsstätten/Schulen für Gesundheits-/Sozialberufe'],
            ['typ' => 'beruf', 'bezeichnung' => 'zwei-/dreijährige Programme an Ausbildungsstätten/Schulen für Gesundheits-/Sozialberufe'],
            ['typ' => 'beruf', 'bezeichnung' => 'Berufsfachschule (Zweitausbildung nach Erwerb einer Studienberechtigung)'],
            ['typ' => 'beruf', 'bezeichnung' => 'berufliche Programme, die einen Berufsabschluss aber keine Studienberechtigung vermitteln'],
            ['typ' => 'beruf', 'bezeichnung' => 'Fachschulen (o. Gesundheits-/Sozialberufe, Erzieherausbildung), inkl. Meisterausbildung, Technikerausbildung'],
            ['typ' => 'beruf', 'bezeichnung' => 'Ausbildungsstätten/Schulen für Erzieher/-innen'],

            // 🎓 Hochschulabschlüsse
            ['typ' => 'hochschule', 'bezeichnung' => 'Bachelor'],
            ['typ' => 'hochschule', 'bezeichnung' => 'zweiter Bachelorstudiengang'],
            ['typ' => 'hochschule', 'bezeichnung' => 'Diplom (FH)-Studiengang'],
            ['typ' => 'hochschule', 'bezeichnung' => 'Diplom (Universität)-Studiengang'],
            ['typ' => 'hochschule', 'bezeichnung' => 'zweiter Diplom-Studiengang'],
            ['typ' => 'hochschule', 'bezeichnung' => 'Masterstudiengang an Universitäten, Fachhochschulen, Berufsakademien'],
            ['typ' => 'hochschule', 'bezeichnung' => 'zweiter Masterstudiengang'],
            ['typ' => 'hochschule', 'bezeichnung' => 'Promotionsstudium'],
        ]);

        DB::table('zielgruppes')->insert([
            ['bezeichnung' => 'Arbeitslos'],
            ['bezeichnung' => 'Langzeitarbeitslos'],
            ['bezeichnung' => 'Nichterwerbstätig'],
            ['bezeichnung' => 'Nichterwerbstätige, die keine schriftliche oder berufliche Ausbildung absolvieren'],
            ['bezeichnung' => 'Erwerbstätige'],
            ['bezeichnung' => 'Selbstständige'],
            ['bezeichnung' => 'Auszubildende'],
            ['bezeichnung' => 'Berufsschüler'],
            ['bezeichnung' => 'Schüler allgemeinbildender Schulen'],
            ['bezeichnung' => 'Menschen mit Migrationshintergrund'],
            ['bezeichnung' => 'Flüchtinge'],
            ['bezeichnung' => 'KMU'],
        ]);
        DB::table('verbleibteilnehmers')->insert([
            ['bezeichnung' => 'auf Arbeitssuche'],
            ['bezeichnung' => 'absolviert eine schulische/berufliche Ausbildung'],
            ['bezeichnung' => 'erlangt eine Qualifizierung'],
            ['bezeichnung' => 'hat einen Arbeitsplatz'],
            ['bezeichnung' => 'hat eine Selbstständigkeit aufgenommen / selbstständig'],
            ['bezeichnung' => 'sonstiger Verbleib'],
            ['bezeichnung' => 'Wechsel in Folgemaßnahme'],
        ]);



        DB::table('ergebnisses')->insert([
            ['bezeichnung' => 'Teilnahme läuft'],
            ['bezeichnung' => 'hat eine Qualifizierung erhalten'],
            ['bezeichnung' => 'einfacher TN-Nachweis'],
            ['bezeichnung' => 'hat Beratung erhalten'],
            ['bezeichnung' => 'hat eine Qualifikationsanerkennung erreicht'],
            ['bezeichnung' => 'absolviert eine schul./berufl. Bildung'],
            ['bezeichnung' => 'hat einen Arbeitsplatz'],
            ['bezeichnung' => 'hat einen Ausbildungsabschluss erlangt'],
            ['bezeichnung' => 'hat ein Studium aufgenommen'],
            ['bezeichnung' => '(Stufen-)Zeugnis der Berufsfachschule/Ausbildungsvorbereitung/Werkstattschule'],
            ['bezeichnung' => 'Abschluss erst in Folgemaßnahme'],
            ['bezeichnung' => 'hat Teilnahme abgebrochen'],

        ]);





        DB::table('standorts')->insert([
            [ // id = 1
                'name' => 'BOP',
            ],
            [ // id = 2
                'name' => 'Verwaltung',
            ],
            [ // id = 3
                'name' => 'Völklingen',
            ],
            [ // id = 4
                'name' => 'Brebach',
            ],
        ]);
          $personen =
        [
            ['vorname' => 'Amin', 'nachname' => 'Masri', 'geburtsdatum' => '2000-01-01', 'email' => 'amin.masri@outlook.com', 'username' => 'aminmasri', 'password' => 'password'],
            ['vorname' => 'Anika', 'nachname' => 'Feller', 'geburtsdatum' => '2000-01-01', 'email' => 'a.feller@zbb-saar.de', 'username' => 'Anika Feller', 'password' => 'zbb.bop.hw'],
            ['vorname' => 'Salvatore', 'nachname' => 'Gucciardo', 'geburtsdatum' => '2000-01-01', 'email' => 's.gucciardo@zbb-saar.de', 'username' => 'Salvatore Gucciardo', 'password' => 'zbb.bop.ala'],
            ['vorname' => 'Birgitta', 'nachname' => 'Lautenschlager', 'geburtsdatum' => '2000-01-01', 'email' => 'b.lautenschlager@zbb-saar.de', 'username' => 'Brigitta Lautenschlager', 'password' => 'zbb.al'],
            ['vorname' => 'Chantale', 'nachname' => 'Lismann', 'geburtsdatum' => '2000-01-01', 'email' => 'c.lismann@zbb-saar.de', 'username' => 'Chantale Lismann', 'password' => 'zbb.al'],
            ['vorname' => 'Stefanie', 'nachname' => 'Wagner', 'geburtsdatum' => '2000-01-01', 'email' => 's.wagner@zbb-saar.de', 'username' => 'Stefanie Wagner', 'password' => 'zbb.al'],
            ['vorname' => 'Stefan', 'nachname' => 'Haßdenteufel', 'geburtsdatum' => '2000-01-01', 'email' => 's.haßdenteufel@zbb-saar.de', 'username' => 'Stefan Haßdenteufel', 'password' => 'zbb.al'],
            ['vorname' => 'Martin', 'nachname' => 'Löw', 'geburtsdatum' => '2000-01-01', 'email' => 'm.loew@zbb-saar.de', 'username' => 'Martin Löw', 'password' => 'zbb.al'],
        ];

        $projektIds = Projekt::pluck('id')->toArray();

        foreach ($personen as $person)
        {
            // Person einfügen und ID speichern
            $personId = DB::table('personens')->insertGetId([
                'vorname' => $person['vorname'],
                'nachname' => $person['nachname'],
                'geburtsdatum' => $person['geburtsdatum'],
            ]);

                // Passendes Benutzerkonto automatisch anlegen
                DB::table('users')->insert([
                    'person_id' => $personId,
                    'username' => $person['username'],
                    'email' => $person['email'],
                    'password' => Hash::make($person['password']),
                    'lang' => 'de',
                    'default_projekt_id' => $personId == 1 || $personId == 2 ? 5 : fake()->randomElement($projektIds),
                ]);

                DB::table('standort_has_personens')->insert([
                    [ // id = 1
                        'personen_id' => $personId,
                        'standort_id' => fake()->randomElement(Standort::pluck('id')->toArray()),
                    ],
                ]);
        }

        DB::table('kontakttypens')->insert([
            [ // id = 1
                'name' => 'Mobile',
            ],
            [ // id = 2
                'name' => 'Telefon',
            ],
            [ // id = 3
                'name' => 'Email',
            ],
            [ // id = 4
                'name' => 'Linkedin',
            ],
        ]);

        DB::table('abteilungs')->insert([
            [ // id = 1
                'name' => 'Abt. Übergang Schule-Beruf',
                'personen_id' => '4',
            ],
            [ // id = 2
                'name' => 'Abt. Aus- und Weiterbildung',
                'personen_id' => '6',
            ],
            [ // id = 3
                'name' => 'Abt. Arbeit- und Lernen',
                'personen_id' => '7',
            ],
            [ // id = 4
                'name' => 'Abt. Beratung, Integration & Vermittlung',
                'personen_id' => '8',
            ],

        ]);



        DB::table('projekts')->insert([
            [ // id = 1
                'name' => 'Inteqra',
                'abteilung_id' => '1',
            ],
            [ // id = 2
                'name' => 'BvB Reha',
                'abteilung_id' => '1',
            ],
            [ // id = 3
                'name' => 'Aques',
                'abteilung_id' => '1',
            ],
            [ // id = 4
                'name' => 'Intqra PRO',
                'abteilung_id' => '1',
            ],

            [ // id = 5
                'name' => 'Bop',
                'abteilung_id' => '1',
            ],
            [ // id = 6
                'name' => 'Sofia',
                'abteilung_id' => '1',
            ],
            [ // id = 7
                'name' => 'BIG Saar',
                'abteilung_id' => '1',
            ],
            [ // id = 8
                'name' => 'Familien Info Saarbrücken',
                'abteilung_id' => '1',
            ],
            [ // id = 9
                'name' => 'Kakadu',
                'abteilung_id' => '1',
            ],
        ]);

        DB::table('bereiches')->insert([
            [ // id = 1
                'name' => 'IT-und Mediengestaltung',
            ],
            [ // id = 2
                'name' => 'Friseur/Kosmetik/Körperpflege',
            ],
            [ // id = 3
                'name' => 'Holztechnik',
            ],
            [ // id = 4
                'name' => 'Metaltechnik',
            ],
            [ // id = 5
                'name' => 'Hauswirtschaft',
            ],
            [ // id = 6
                'name' => 'Verkauf und Wirtschaft',
            ],
            [ // id = 7
                'name' => 'Lager und Handel',
            ],
            [ // id = 8
                'name' => 'Maler und Lackierer',
            ],
            [ // id = 9
                'name' => 'Garten und Landschaftsbau',
            ],
            [ // id = 10
                'name' => 'Buromanagement',
            ],

        ]);



        DB::table('berechtigungskategories')->insert([

            [ // id = 1
                'name' => 'Dashboard',
                'beschreibung' => '',
            ],
            [ // id = 2
                'name' => 'Kooperationspartner',
                'beschreibung' => '',
            ],
            [ // id = 3
                'name' => 'Gruppe',
                'beschreibung' => '',
            ],
            [ // id = 4
                'name' => 'Bereich',
                'beschreibung' => '',
            ],
            [ // id = 5
                'name' => 'Teilnehmer',
                'beschreibung' => '',
            ],
            [ // id = 6
                'name' => 'TLN-GRP',
                'beschreibung' => '',
            ],
            [ // id = 7
                'name' => 'Rolle',
                'beschreibung' => '',
            ],
            [ // id = 8
                'name' => 'Permission',
                'beschreibung' => '',
            ],
            [ // id = 9
                'name' => 'Benutzer',
                'beschreibung' => '',
            ],
            [ // id = 10
                'name' => 'Auswertung',
                'beschreibung' => '',
            ],
            [ // id = 11
                'name' => 'Anwesenheitsliste',
                'beschreibung' => '',
            ],
            [ // id = 12
                'name' => 'Einteilung',
                'beschreibung' => '',
            ],
            [ // id = 13
                'name' => 'Bereichauswahl',
                'beschreibung' => '',
            ],
            [ // id = 14
                'name' => 'Dateimanager',
                'beschreibung' => '',
            ],
            [ // id = 15
                'name' => 'Kalender',
                'beschreibung' => '',
            ],
            [ // id = 16
                'name' => 'Kontakte',
                'beschreibung' => '',
            ],
            [ // id = 17
                'name' => 'Taskmanager',
                'beschreibung' => '',
            ],
            [ // id = 18
                'name' => 'Abteilung',
                'beschreibung' => '',
            ],
            [ // id = 19
                'name' => 'Projekt',
                'beschreibung' => '',
            ],
            [ // id = 20
                'name' => 'Geraet',
                'beschreibung' => '',
            ],
            [ // id = 21
                'name' => 'Standort',
                'beschreibung' => '',
            ],

        ]);

        DB::table('roles')->insert([
            [ // id = 1
                'name' => 'Administrator',
                'guard_name' => 'web',
                'color' => 'bg-orange-200',
            ],
            [ // id = 2
                'name' => 'Abteilungsleitung',
                'guard_name' => 'web',
                'color' => 'bg-green-200',
            ],
            [ // id = 3
                'name' => 'Assistenz der Abt.-Leitung',
                'guard_name' => 'web',
                'color' => 'bg-yellow-200',
            ],
            [ // id = 4
                'name' => 'Sozialpädagoge',
                'guard_name' => 'web',
                'color' => 'bg-slate-400',
            ],
            [ // id = 5
                'name' => 'Anleiter',
                'guard_name' => 'web',
                'color' => 'bg-cyan-300',
            ],
            [ // id = 6
                'name' => 'Sekretariat',
                'guard_name' => 'web',
                'color' => 'bg-rose-400',
            ],
            [ // id = 7
                'name' => 'Developer',
                'guard_name' => 'web',
                'color' => 'bg-red-200',
            ],

        ]);

        DB::table('permissions')->insert([
            [ // id = 1
                'name' => 'dashboard.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '1',
            ],
            [ // id = 2
                'name' => 'berechtigung.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '8',
            ],
            [ // id = 3
                'name' => 'berechtigung.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '8',
            ],

            [ // id = 4
                'name' => 'benutzer.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '9',

            ],
            [ // id = 5
                'name' => 'benutzer.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '9',

            ],
            [ // id = 6
                'name' => 'benutzer.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '9',

            ],
            [ // id = 7
                'name' => 'benutzer.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '9',

            ],

             [ // id = 8
                'name' => 'kooperationspartner.index',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',

            ],
            [ // id = 9
                'name' => 'kooperationspartner.store',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',

            ],
            [ // id = 10
                'name' => 'kooperationspartner.update',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',

            ],
            [ // id = 11
                'name' => 'kooperationspartner.destroy',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',
            ],

            [ // id = 12
                'name' => 'bereich.index',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',
            ],
            [ // id = 13
                'name' => 'bereich.store',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',
            ],
            [ // id = 14
                'name' => 'bereich.destroy',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',
            ],
            [ // id = 15
                'name' => 'projekt.index',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '19',
            ],
            [ // id = 16
                'name' => 'projekt.store',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '19',
            ],
            [ // id = 17
                'name' => 'projekt.destroy',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '19',
            ],
            [ // id = 18
                'name' => 'abteilung.index',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '18',
            ],
            [ // id = 19
                'name' => 'abteilung.store',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '18',
            ],
            [ // id = 20
                'name' => 'abteilung.destroy',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '18',
            ],
            [ // id = 21
                'name' => 'standort.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '21',
            ],
            [ // id = 22
                'name' => 'standort.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '21',
            ],
            [ // id = 23
                'name' => 'standort.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '21',
            ],
            [ // id = 24
                'name' => 'standort.delete',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '21',
            ],

            [ // id = 25
                'name' => 'teilnehmer.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '5',
            ],

            [ // id = 26
                'name' => 'teilnehmer.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '5',
            ],

            [ // id = 27
                'name' => 'teilnehmer.view.all',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '5',
            ],







        ]);

        DB::table('model_has_roles')->insert([
            [
                'role_id' => '1',
                'model_type' => 'App\Models\User',
                'model_id' => '1'
            ],
            [
                'role_id' => '5',
                'model_type' => 'App\Models\User',
                'model_id' => '2'
            ],
            [
                'role_id' => '3',
                'model_type' => 'App\Models\User',
                'model_id' => '3'
            ],
            [
                'role_id' => '2',
                'model_type' => 'App\Models\User',
                'model_id' => '4'
            ],
            [
                'role_id' => '2',
                'model_type' => 'App\Models\User',
                'model_id' => '5'
            ],
        ]);

        DB::table('role_has_permissions')->insert([
            [
                'permission_id' => '1',
                'role_id' => '1',
            ],
            [
                'permission_id' => '2',
                'role_id' => '1',
            ],
            [
                'permission_id' => '3',
                'role_id' => '1',
            ],
            [
                'permission_id' => '4',
                'role_id' => '1',
            ],
            [
                'permission_id' => '5',
                'role_id' => '1',
            ],
            [
                'permission_id' => '6',
                'role_id' => '1',
            ],
            [
                'permission_id' => '7',
                'role_id' => '1',
            ],
            [
                'permission_id' => '8',
                'role_id' => '1',
            ],
            [
                'permission_id' => '9',
                'role_id' => '1',
            ],
            [
                'permission_id' => '10',
                'role_id' => '1',
            ],
            [
                'permission_id' => '11',
                'role_id' => '1',
            ],
            [
                'permission_id' => '12',
                'role_id' => '1',
            ],
            [
                'permission_id' => '13',
                'role_id' => '1',
            ],
            [
                'permission_id' => '14',
                'role_id' => '1',
            ],
            [
                'permission_id' => '15',
                'role_id' => '1',
            ],
            [
                'permission_id' => '16',
                'role_id' => '1',
            ],
            [
                'permission_id' => '17',
                'role_id' => '1',
            ],
            [
                'permission_id' => '18',
                'role_id' => '1',
            ],
            [
                'permission_id' => '19',
                'role_id' => '1',
            ],
            [
                'permission_id' => '20',
                'role_id' => '1',
            ],
            [
                'permission_id' => '21',
                'role_id' => '1',
            ],


        ]);

        DB::table('role_berechtigungskategories')->insert([
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '1',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '2',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '3',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '4',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '5',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '6',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '7',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '8',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '9',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '10',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '11',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '12',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '13',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '14',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '15',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '16',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '17',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '18',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '19',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '20',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '21',
            ],



        ]);

        DB::table('projekt_has_personens')->insert([
            [
                'personen_id' => '1',
                'projekt_id' => '1',
            ],
            [
                'personen_id' => '1',
                'projekt_id' => '2',
            ],
            [
                'personen_id' => '1',
                'projekt_id' => '3',
            ],
            [ // id = 1
                'personen_id' => '1',
                'projekt_id' => '5',
            ],

        ]);

        //Teilnehmer erstellen und mit Projekten verknüpfen
        $faker = Faker::create();
        // Anzahl der Benutzer, die erstellt werden sollen
        $numberOfUsers = 50;
        for ($i = 0; $i < $numberOfUsers; $i++)
        {
            $teilnehmer = Personen::create([
                'vorname' => $faker->firstName,
                'nachname' => $faker->lastName(),
                'geschlecht' => $faker->randomElement(['m', 'd', 'w']),
                'geburtsdatum' => '2000-10-10',
                'typ' => 'teilnehmer',
            ]);

             // Projekt-IDs und Teilnehmer-IDs müssen aus DB kommen
            ProjektHasPersonen::create([
                'projekt_id'    => $faker->randomElement(Projekt::pluck('id')->toArray()),
                'personen_id' => $teilnehmer->id, // gerade erstellter Teilnehmer
            ]);
            StandortHasPersonen::create([
                'standort_id'    => $faker->randomElement(Standort::pluck('id')->toArray()),
                'personen_id' => $teilnehmer->id, // gerade erstellter Teilnehmer

            ]);
        };

    }
}
