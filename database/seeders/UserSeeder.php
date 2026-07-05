<?php

namespace Database\Seeders;

use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\StandortHasPersonen;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        DB::table('partnerschaftstypens')->insert([
            [
                'bezeichnung' => 'Bildungspartner',
                'beschreibung' => 'Schulen, Hochschulen oder Bildungseinrichtungen, mit denen organisatorisch zusammengearbeitet wird.',
            ],
            [
                'bezeichnung' => 'Kooperationsschule',
                'beschreibung' => 'Schulen, die in Kooperation mit anderen Einrichtungen tätig sind, wie BOP',
            ],
            [
                'bezeichnung' => 'Praktikumspartner',
                'beschreibung' => 'Unternehmen, die regelmäßig Praktikumsplätze zur Verfügung stellen.',
            ],

            [
                'bezeichnung' => 'Kooperationsbetrieb',
                'beschreibung' => 'Langfristige betriebliche Zusammenarbeit im Rahmen von Projekten oder Ausbildungsprogrammen.',
            ],

            [
                'bezeichnung' => 'Förderpartner',
                'beschreibung' => 'Partner, die finanzielle oder materielle Unterstützung leisten.',
            ],

            [
                'bezeichnung' => 'Projektpartner',
                'beschreibung' => 'Externe Einrichtungen, die an gemeinsamen Projekten beteiligt sind.',
            ],

            [
                'bezeichnung' => 'Sozialer Träger',
                'beschreibung' => 'Sozialdienste, Jugendämter oder Hilfsorganisationen mit enger Zusammenarbeit.',
            ],

            [
                'bezeichnung' => 'Beratungspartner',
                'beschreibung' => 'Externe Beratungsstellen oder Experten, die fachliche Unterstützung leisten.',
            ],

            [
                'bezeichnung' => 'Netzwerkpartner',
                'beschreibung' => 'Teil eines gemeinsamen Netzwerks zur beruflichen oder sozialen Förderung.',
            ],

            [
                'bezeichnung' => 'Bildungscoach / Mentor',
                'beschreibung' => 'Einrichtungen oder Personen, die Coaching oder Mentoring anbieten.',
            ],

            [
                'bezeichnung' => 'Reha-Partner',
                'beschreibung' => 'Kooperation im Rahmen beruflicher Rehabilitation (z. B. Reha-Träger).',
            ],

            [
                'bezeichnung' => 'Wirtschaftspartner',
                'beschreibung' => 'Unternehmen oder Verbände aus der regionalen Wirtschaft.',
            ],

            [
                'bezeichnung' => 'Arbeitsvermittler',
                'beschreibung' => 'Partner, die bei der Vermittlung in Arbeit oder Ausbildung unterstützen.',
            ],

            [
                'bezeichnung' => 'Weiterbildungspartner',
                'beschreibung' => 'Organisationen, die Weiterbildungsangebote bereitstellen.',
            ],

            [
                'bezeichnung' => 'Integrationspartner',
                'beschreibung' => 'Partner zur Integration in Arbeit, Ausbildung oder Gesellschaft.',
            ],

            [
                'bezeichnung' => 'Gesundheitspartner',
                'beschreibung' => 'Ärzte, Kliniken, Gesundheitsdienste oder Beratungen.',
            ],

            [
                'bezeichnung' => 'Kommunaler Partner',
                'beschreibung' => 'Ämter, Behörden oder kommunale Stellen.',
            ],

            [
                'bezeichnung' => 'Bildungsförderung',
                'beschreibung' => 'Organisationen, die Bildung finanziell oder materiell unterstützen.',
            ],

        ]);

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
        DB::table('standorts')->insertOrIgnore([
            ['name' => 'BOP'],
            ['name' => 'Verwaltung'],
            ['name' => 'Völklingen'],
            ['name' => 'Brebach'],
        ]);
        $personen =
        [
          ['typ' => 'mitarbeiter', 'vorname' => 'Amin', 'nachname' => 'Masri', 'geschlecht' => 'm', 'geburtsdatum' => '2000-01-01', 'email' => 'amin.masri@outlook.com', 'username' => 'aminmasri', 'password' => 'password'],
          ['typ' => 'mitarbeiter', 'vorname' => 'Anika', 'nachname' => 'Feller', 'geschlecht' => 'w', 'geburtsdatum' => '2000-01-01', 'email' => 'a.feller@zbb-saar.de', 'username' => 'Anika Feller', 'password' => 'zbb.bop.hw'],
          ['typ' => 'mitarbeiter', 'vorname' => 'Salvatore', 'nachname' => 'Gucciardo', 'geschlecht' => 'm', 'geburtsdatum' => '2000-01-01', 'email' => 's.gucciardo@zbb-saar.de', 'username' => 'Salvatore Gucciardo', 'password' => 'zbb.bop.ala'],
          ['typ' => 'mitarbeiter', 'vorname' => 'Birgitta', 'nachname' => 'Lautenschlager', 'geschlecht' => 'w', 'geburtsdatum' => '2000-01-01', 'email' => 'b.lautenschlager@zbb-saar.de', 'username' => 'Brigitta Lautenschlager', 'password' => 'zbb.al'],
          ['typ' => 'mitarbeiter', 'vorname' => 'Chantale', 'nachname' => 'Lismann', 'geschlecht' => 'w', 'geburtsdatum' => '2000-01-01', 'email' => 'c.lismann@zbb-saar.de', 'username' => 'Chantale Lismann', 'password' => 'zbb.al'],
          ['typ' => 'mitarbeiter', 'vorname' => 'Stefanie', 'nachname' => 'Wagner', 'geschlecht' => 'w', 'geburtsdatum' => '2000-01-01', 'email' => 's.wagner@zbb-saar.de', 'username' => 'Stefanie Wagner', 'password' => 'zbb.al'],
          ['typ' => 'mitarbeiter', 'vorname' => 'Stefan', 'nachname' => 'Haßdenteufel', 'geschlecht' => 'm', 'geburtsdatum' => '2000-01-01', 'email' => 's.haßdenteufel@zbb-saar.de', 'username' => 'Stefan Haßdenteufel', 'password' => 'zbb.al'],
          ['typ' => 'mitarbeiter', 'vorname' => 'Martin', 'nachname' => 'Löw', 'geschlecht' => 'm', 'geburtsdatum' => '2000-01-01', 'email' => 'm.loew@zbb-saar.de', 'username' => 'Martin Löw', 'password' => 'zbb.al'],
        ];

        $projektIds = Projekt::pluck('id')->toArray();

        foreach ($personen as $person) {
            // Person einfügen und ID speichern
            $personId = DB::table('personens')->insertGetId([
                'vorname' => $person['vorname'],
                'nachname' => $person['nachname'],
                'geburtsdatum' => $person['geburtsdatum'],
                'geschlecht' => $person['geschlecht'],
                'typ' => $person['typ'],

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

        DB::table('notizvariantens')->insert([
            // Notizkategorie
            [ // id = 1
                'typ' => 'kategorie',
                'name' => 'Beratung',
            ],
            [ // id = 2
                'typ' => 'kategorie',
                'name' => 'Fortschritt',
            ],
            [ // id = 3
                'typ' => 'kategorie',
                'name' => 'Problem',
            ],
            [ // id = 4
                'typ' => 'kategorie',
                'name' => 'Termin',
            ],

            // Notiztyp
            [ // id = 5
                'typ' => 'typ',
                'name' => 'Aktennotiz',
            ],
            [ // id = 6
                'typ' => 'typ',
                'name' => 'Beratungsprotokoll',
            ],
            [ // id = 7
                'typ' => 'typ',
                'name' => 'Verlaufsnotiz',
            ],
            [ // id = 8
                'typ' => 'typ',
                'name' => 'Telefonnotiz',
            ],

            // Priorität
            [ // id = 9
                'typ' => 'prioritaet',
                'name' => 'Niedrig',
            ],
            [ // id = 10
                'typ' => 'prioritaet',
                'name' => 'Mittel',
            ],
            [ // id = 11
                'typ' => 'prioritaet',
                'name' => 'Hoch',
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

        DB::table('projekt_has_bereiches')->insert([
            [ // id = 1
                'projekt_id' => 1,
                'bereich_id' => 1,
                'aktiv' => 1,
            ],
            [ // id = 2
                'projekt_id' => 1,
                'bereich_id' => 2,
                'aktiv' => 1,
            ],
            [ // id = 3
                'projekt_id' => 1,
                'bereich_id' => 3,
                'aktiv' => 1,
            ],
            [ // id = 4
                'projekt_id' => 1,
                'bereich_id' => 4,
                'aktiv' => 1,
            ],
            [ // id = 5
                'projekt_id' => 1,
                'bereich_id' => 5,
                'aktiv' => 1,
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
            [ // id = 22
                'name' => 'Fahrkarten',
                'beschreibung' => '',
            ],
            [ // id = 23
                'name' => 'Printing',
                'beschreibung' => '',
            ],
            [ // id = 24
                'name' => 'Räumlichkeiten',
                'beschreibung' => '',
            ],
            [ // id = 25
                'name' => 'Dienstwagen',
                'beschreibung' => '',
            ],
            [ // id = 26
                'name' => 'Personal',
                'beschreibung' => '',
            ],
            [
                // id = 27
                'name' => 'Bestellungen',
                'beschreibung' => '',
            ],

        ]);

        DB::table('roles')->insert([
            [ // id = 1
                'name' => 'Administrator',
                'guard_name' => 'web',
                'color' => 'bg-orange-200',
            ],
            [
                'name' => 'Pädagogische Leitung',
                'guard_name' => 'web',
                'color' => 'bg-blue-200',
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

            [
                // id = 4
                'name' => 'Projektleitung',
                'guard_name' => 'web',
                'color' => 'bg-indigo-300',
            ],

            [ // id = 5
                'name' => 'Sozialpädagoge',
                'guard_name' => 'web',
                'color' => 'bg-slate-400',
            ],

            [
                'name' => 'Ausbilder',
                'guard_name' => 'web',
                'color' => 'bg-lime-300',
            ],

            [ // id = 5
                'name' => 'Anleiter',
                'guard_name' => 'web',
                'color' => 'bg-cyan-300',
            ],

            [
                'name' => 'Auszubildender',
                'guard_name' => 'web',
                'color' => 'bg-emerald-300',
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
            [
                'name' => 'Personalabteilung',
                'guard_name' => 'web',
                'color' => 'bg-violet-300',
            ],
            [
                'name' => 'Bestellwesen',
                'guard_name' => 'web',
                'color' => 'bg-fuchsia-300',
            ],
            [
                'name' => 'Buchhaltung',
                'guard_name' => 'web',
                'color' => 'bg-amber-300',
            ],
            [
                'name' => 'Sicherheitsbeauftragte',
                'guard_name' => 'web',
                'color' => 'bg-rose-400',
            ],
            [
                'name' => 'Ersthelfer',
                'guard_name' => 'web',
                'color' => 'bg-rose-400',
            ],
            [
                'name' => 'Qualitätsmanagement',
                'guard_name' => 'web',
                'color' => 'bg-violet-300',
            ]

        ]);

        DB::table('permissions')->insert([
            [ // id = 1
                'name' => 'dashboard.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '1',
                'beschreibung' => null,
            ],
            [ // id = 2
                'name' => 'berechtigung.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '8',
                'beschreibung' => null,
            ],
            [ // id = 3
                'name' => 'berechtigung.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '8',
                'beschreibung' => null,
            ],

            [ // id = 4
                'name' => 'benutzer.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '9',
                'beschreibung' => null,
            ],
            [ // id = 5
                'name' => 'benutzer.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '9',
                'beschreibung' => null,
            ],
            [ // id = 6
                'name' => 'benutzer.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '9',
                'beschreibung' => null,
            ],
            [ // id = 7
                'name' => 'benutzer.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '9',
                'beschreibung' => null,
            ],

            [ // id = 8
                'name' => 'kooperationspartner.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '2',
                'beschreibung' => null,
            ],
            [ // id = 9
                'name' => 'kooperationspartner.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '2',
                'beschreibung' => null,
            ],
            [ // id = 10
                'name' => 'kooperationspartner.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '2',
                'beschreibung' => null,
            ],
            [ // id = 11
                'name' => 'kooperationspartner.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '2',
                'beschreibung' => null,
            ],

            [ // id = 12
                'name' => 'bereich.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '2',
                'beschreibung' => null,
            ],
            [ // id = 13
                'name' => 'bereich.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '2',
                'beschreibung' => null,
            ],
            [ // id = 14
                'name' => 'bereich.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '2',
                'beschreibung' => null,
            ],
            [ // id = 15
                'name' => 'projekt.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '19',
                'beschreibung' => null,
            ],
            [ // id = 16
                'name' => 'projekt.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '19',
                'beschreibung' => null,
            ],
            [ // id = 17
                'name' => 'projekt.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '19',
                'beschreibung' => null,
            ],
            [ // id = 18
                'name' => 'abteilung.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '18',
                'beschreibung' => null,
            ],
            [ // id = 19
                'name' => 'abteilung.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '18',
                'beschreibung' => null,
            ],
            [ // id = 20
                'name' => 'abteilung.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '18',
                'beschreibung' => null,
            ],
            [ // id = 21
                'name' => 'standort.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '21',
                'beschreibung' => null,
            ],
            [ // id = 22
                'name' => 'standort.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '21',
                'beschreibung' => null,
            ],
            [ // id = 23
                'name' => 'standort.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '21',
                'beschreibung' => null,
            ],
            [ // id = 24
                'name' => 'standort.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '21',
                'beschreibung' => null,
            ],

            [ // id = 25
                'name' => 'teilnehmer.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '5',
                'beschreibung' => null,
            ],

            [ // id = 26
                'name' => 'teilnehmer.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '5',
                'beschreibung' => null,
            ],

            [ // id = 27
                'name' => 'teilnehmer.view.all',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '5',
                'beschreibung' => null,
            ],

            [ // id = 28
                'name' => 'fahrtarten.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '22',
                'beschreibung' => null,
            ],

            [ // id = 29
                'name' => 'fahrtarten.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '22',
                'beschreibung' => null,
            ],
            [ // id = 30
                'name' => 'fahrtarten.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '22',
                'beschreibung' => null,
            ],
            [ // id = 31
                'name' => 'fahrtarten.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '22',
                'beschreibung' => null,
            ],
            [ // id = 32
                'name' => 'printing.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '23',
                'beschreibung' => null,
            ],
            [ // id = 33
                'name' => 'printing.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '23',
                'beschreibung' => null,
            ],
            [ // id = 34
                'name' => 'printing.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '23',
                'beschreibung' => null,
            ],
            [ // id = 35
                'name' => 'printing.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '23',
                'beschreibung' => null,
            ],
            [ // id = 36
                'name' => 'räumlichkeiten.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '24',
                'beschreibung' => null,
            ],
            [ // id = 37
                'name' => 'räumlichkeiten.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '24',
                'beschreibung' => null,
            ],
            [ // id = 38
                'name' => 'räumlichkeiten.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '24',
                'beschreibung' => null,
            ],
            [ // id = 39
                'name' => 'räumlichkeiten.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '24',
                'beschreibung' => null,
            ],
            [ // id = 40
                'name' => 'dienstwagen.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '25',
                'beschreibung' => null,
            ],
            [ // id = 41
                'name' => 'dienstwagen.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '25',
                'beschreibung' => null,
            ],
            [ // id = 42
                'name' => 'dienstwagen.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '25',
                'beschreibung' => null,
            ],
            [ // id = 43
                'name' => 'dienstwagen.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '25',
                'beschreibung' => null,
            ],
            [ // id = 44
                'name' => 'dienstwagen.wartung.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '25',
                'beschreibung' => null,
            ],
            [ // id = 45
                'name' => 'dienstwagen.reports.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '25',
                'beschreibung' => null,
            ],
            [ // id = 46
                'name' => 'dienstwagen.fahrtenbuch.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '25',
                'beschreibung' => null,
            ],
            [ // id = 47
                'name' => 'dienstwagen.fahrtenbuch.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '25',
                'beschreibung' => null,
            ],
            [ // id = 48
                'name' => 'dienstwagen.fahrtenbuch.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '25',
                'beschreibung' => null,
            ],
            [ // id = 49
                'name' => 'dienstwagen.fahrtenbuch.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '25',
                'beschreibung' => null,
            ],
            [ // id = 50
                'name' => 'gruppe.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '3',
                'beschreibung' => null,
            ],
            [ // id = 51
                'name' => 'gruppe.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '3',
                'beschreibung' => null,
            ],
            [ // id = 52
                'name' => 'gruppe.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '3',
                'beschreibung' => null,
            ],
            [ // id = 53
                'name' => 'gruppe.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '3',
                'beschreibung' => null,
            ],
            [ // id = 54
                'name' => 'dienstwagen.fahrtenbuch.view.all',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '25',
                'beschreibung' => null,
            ],
            [ // id = 55
                'name' => 'dienstwagen.fahrtenbuch.view.abteilung',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '5',
                'beschreibung' => null,
            ],
            [ // id = 56
                'name' => 'dienstwagen.fahrtenbuch.view.projekt',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '18',
                'beschreibung' => null,
            ],
            [ // id = 57
                'name' => 'finanzen.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '10',
                'beschreibung' => null,
            ],

            [ // id = 58
                'name' => 'teilnehmer.view.abteilung',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '5',
                'beschreibung' => null,
            ],
            [ // id = 59
                'name' => 'teilnehmer.view.projekt',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '5',
                'beschreibung' => null,
            ],
            [ // id = 60
                'name' => 'teilnehmer.view.standort',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '5',
                'beschreibung' => null,
            ],
            [ // id = 70
                'name' => 'personal.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '26',
                'beschreibung' => null,
            ],
            [ // id = 71
                'name' => 'personal.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '26',
                'beschreibung' => null,
            ],
            [ // id = 72
                'name' => 'personal.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '26',
                'beschreibung' => null,
            ],
            [ // id = 73
                'name' => 'personal.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '26',
                'beschreibung' => null,
            ],
            [ // id = 74
                'name' => 'geraet.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '20',
                'beschreibung' => null,
            ],
            [ // id = 75
                'name' => 'geraet.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '20',
                'beschreibung' => null,
            ],
            [ // id = 76
                'name' => 'geraet.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '20',
                'beschreibung' => null,
            ],
            [ // id = 77
                'name' => 'geraet.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '20',
                'beschreibung' => null,
            ],
            [ // id = 78
                'name' => 'geraet.ausgabe.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '20',
                'beschreibung' => null,
            ],
            [ // id = 79
                'name' => 'geraet.ausgabe.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '20',
                'beschreibung' => null,
            ],
            [ // id = 80
                'name' => 'geraet.ausgabe.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '20',
                'beschreibung' => null,
            ],
            [ // id = 81
                'name' => 'geraet.ausgabe.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '20',
                'beschreibung' => null,
            ],
            [
                // id = 82
                'name' => 'geraet.rueckgabe.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '20',
                'beschreibung' => null,
            ],
            [
                // id = 83
                'name' => 'geraet.rueckgabe.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '20',
                'beschreibung' => null,
            ],
            [
                // id = 84
                'name' => 'geraet.rueckgabe.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '20',
                'beschreibung' => null,
            ],
            [
                // id = 85
                'name' => 'geraet.rueckgabe.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '20',
                'beschreibung' => null,
            ],
            [
                // id = 86
                'name' => 'materialanforderung.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '27',
                'beschreibung' => null,
            ],
            [
                // id = 87
                'name' => 'materialanforderung.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '27',
                'beschreibung' => null,
            ],
            [
                // id = 88
                'name' => 'materialanforderung.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '27',
                'beschreibung' => null,
            ],
            [
                // id = 89
                'name' => 'materialanforderung.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '27',
                'beschreibung' => null,
            ],
            [
                // id = 90
                'name' => 'materialanforderung.sachlische_freigabe.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '27',
                'beschreibung' => 'Übersicht aller zu prüfenden Anforderungen der Abteilungen',
            ],
            [
                // id = 91
                'name' => 'materialanforderung.sachlische_freigabe.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '27',
                'beschreibung' => 'Eine Anforderung fachlich bearbeiten oder den Status auf "freigegeben" setzen.',
            ],
            [
                // id = 92
                'name' => 'materialanforderung.sachlische_freigabe.show',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '27',
                'beschreibung' => 'Details zur fachlichen Prüfung einsehen.',
            ],
            [
                // id = 93
                'name' => 'materialanforderung.kaufmännische_freigabe.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '27',
                'beschreibung' => 'Übersicht aller fachlich freigegebenen Anforderungen zur Budgetprüfung.',
            ],
            [
                // id = 94
                'name' => 'materialanforderung.kaufmännische_freigabe.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '27',
                'beschreibung' => 'Eine Anforderung kaufmännisch bearbeiten oder den Status auf "freigegeben" setzen.',
            ],
            [
                // id = 95
                'name' => 'materialanforderung.kaufmännische_freigabe.show',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '27',
                'beschreibung' => 'Details zur kaufmännischen Prüfung einsehen.',
            ],
            [
                // id = 96
                'name' => 'materialanforderung.bestellwesen.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '27',
                'beschreibung' => 'eine Anforderung im Bestellwesen bearbeiten oder den Status auf "bestellt" setzen.',
            ],
            [
                'name' => 'gruppe.view.all',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '3',
                'beschreibung' => 'Alle Gruppen im ausgewaehlten Projekt sehen.',
            ],
            [
                'name' => 'raeumlichkeiten.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '24',
                'beschreibung' => 'Raumuebersicht sehen.',
            ],
            [
                'name' => 'raeumlichkeiten.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '24',
                'beschreibung' => 'Raeume anlegen.',
            ],
            [
                'name' => 'raeumlichkeiten.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '24',
                'beschreibung' => 'Raeume bearbeiten.',
            ],
            [
                'name' => 'raeumlichkeiten.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '24',
                'beschreibung' => 'Raeume loeschen.',
            ],
            [
                'name' => 'raeumlichkeiten.meldung.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '24',
                'beschreibung' => 'Schaeden oder Probleme in Raeumen melden.',
            ],
            [
                'name' => 'raeumlichkeiten.meldung.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '24',
                'beschreibung' => 'Raummeldungen bearbeiten oder abschliessen.',
            ],
            [
                'name' => 'teilnehmer.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '5',
                'beschreibung' => 'Teilnehmer bearbeiten.',
            ],
            [
                'name' => 'teilnehmer.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '5',
                'beschreibung' => 'Teilnehmer loeschen.',
            ],

        ]);

        DB::table('model_has_roles')->insert([
            [
                'role_id' => '1',
                'model_type' => 'App\Models\User',
                'model_id' => '1',
            ],
            [
                'role_id' => '5',
                'model_type' => 'App\Models\User',
                'model_id' => '2',
            ],
            [
                'role_id' => '3',
                'model_type' => 'App\Models\User',
                'model_id' => '3',
            ],
            [
                'role_id' => '2',
                'model_type' => 'App\Models\User',
                'model_id' => '4',
            ],
            [
                'role_id' => '2',
                'model_type' => 'App\Models\User',
                'model_id' => '5',
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
            [
                'permission_id' => '22',
                'role_id' => '1',
            ],
            [
                'permission_id' => '23',
                'role_id' => '1',
            ],
            [
                'permission_id' => '24',
                'role_id' => '1',
            ],
            [
                'permission_id' => '25',
                'role_id' => '1',
            ],
            [
                'permission_id' => '26',
                'role_id' => '1',
            ],
            [
                'permission_id' => '27',
                'role_id' => '1',
            ],
            [
                'permission_id' => '28',
                'role_id' => '1',
            ],
            [
                'permission_id' => '29',
                'role_id' => '1',
            ],
            [
                'permission_id' => '30',
                'role_id' => '1',
            ],
            [
                'permission_id' => '31',
                'role_id' => '1',
            ],
            [
                'permission_id' => '32',
                'role_id' => '1',
            ],
            [
                'permission_id' => '33',
                'role_id' => '1',
            ],
            [
                'permission_id' => '34',
                'role_id' => '1',
            ],
            [
                'permission_id' => '35',
                'role_id' => '1',
            ],
            [
                'permission_id' => '36',
                'role_id' => '1',
            ],
            [
                'permission_id' => '37',
                'role_id' => '1',
            ],
            [
                'permission_id' => '38',
                'role_id' => '1',
            ],
            [
                'permission_id' => '39',
                'role_id' => '1',
            ],
            [
                'permission_id' => '40',
                'role_id' => '1',
            ],
            [
                'permission_id' => '41',
                'role_id' => '1',
            ],
            [
                'permission_id' => '42',
                'role_id' => '1',
            ],
            [
                'permission_id' => '43',
                'role_id' => '1',
            ],
            [
                'permission_id' => '44',
                'role_id' => '1',
            ],
            [
                'permission_id' => '45',
                'role_id' => '1',
            ],
            [
                'permission_id' => '46',
                'role_id' => '1',
            ],
            [
                'permission_id' => '47',
                'role_id' => '1',
            ],
            [
                'permission_id' => '48',
                'role_id' => '1',
            ],
            [
                'permission_id' => '49',
                'role_id' => '1',
            ],
            [
                'permission_id' => '50',
                'role_id' => '1',
            ],
            [
                'permission_id' => '51',
                'role_id' => '1',
            ],
            [
                'permission_id' => '52',
                'role_id' => '1',
            ],
            [
                'permission_id' => '53',
                'role_id' => '1',
            ],
            [
                'permission_id' => '54',
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
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '22',
            ],

            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '23',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '24',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '25',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '26',
            ],
            [
                'role_id' => '1',
                'berechtigungskategorie_id' => '27',
            ],
        ]);

        $extraPermissionNames = [
            'gruppe.view.all',
            'raeumlichkeiten.index',
            'raeumlichkeiten.store',
            'raeumlichkeiten.update',
            'raeumlichkeiten.destroy',
            'raeumlichkeiten.meldung.store',
            'raeumlichkeiten.meldung.update',
            'teilnehmer.update',
            'teilnehmer.destroy',
        ];

        $extraPermissionIds = DB::table('permissions')
            ->whereIn('name', $extraPermissionNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        $extraRoleIds = DB::table('roles')
            ->whereIn('name', ['Administrator', 'Developer'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($extraRoleIds as $roleId) {
            foreach ($extraPermissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));

        DB::table('projekt_has_personens')->insert([
            [
                'personen_id' => '1',
                'projekt_id' => '1',
                'standort_id' => '1',

            ],
            [
                'personen_id' => '1',
                'projekt_id' => '2',
                'standort_id' => '1',
            ],
            [
                'personen_id' => '1',
                'projekt_id' => '3',
                'standort_id' => '1',
            ],
            [ // id = 1
                'personen_id' => '1',
                'projekt_id' => '5',
                'standort_id' => '1',
            ],

        ]);

        // Teilnehmer erstellen und mit Projekten verknüpfen
        $faker = Faker::create();
        // Anzahl der Benutzer, die erstellt werden sollen
        $numberOfUsers = 50;
        for ($i = 0; $i < $numberOfUsers; $i++) {
            $teilnehmer = Personen::create([
                'vorname' => $faker->firstName,
                'nachname' => $faker->lastName(),
                'geschlecht' => $faker->randomElement(['m', 'd', 'w']),
                'geburtsdatum' => '2000-10-10',
                'typ' => 'teilnehmer',
            ]);

            // Projekt-IDs und Teilnehmer-IDs müssen aus DB kommen
            ProjektHasPersonen::create([
                'projekt_id' => $faker->randomElement(Projekt::pluck('id')->toArray()),
                'personen_id' => $teilnehmer->id, // gerade erstellter Teilnehmer
                'standort_id' => '1',
            ]);
            StandortHasPersonen::create([
                'standort_id' => $faker->randomElement(Standort::pluck('id')->toArray()),
                'personen_id' => $teilnehmer->id, // gerade erstellter Teilnehmer

            ]);
        }

    }
}
