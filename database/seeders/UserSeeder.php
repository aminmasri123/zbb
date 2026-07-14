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
    private array $permissionCategoryIds = [];

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
        $standortIds = Standort::pluck('id')->toArray();

        foreach ($personen as $person) {
            $existingUser = DB::table('users')->where('email', $person['email'])->first();

            if ($existingUser) {
                $personId = $existingUser->person_id;
            } else {
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
                    'default_projekt_id' => ($personId == 1 || $personId == 2) && in_array(5, $projektIds, true)
                        ? 5
                        : ($projektIds === [] ? null : fake()->randomElement($projektIds)),
                ]);

            }

            if ($personId && $standortIds !== []) {
                $standortId = fake()->randomElement($standortIds);

                if (! DB::table('standort_has_personens')->where('personen_id', $personId)->exists()) {
                    DB::table('standort_has_personens')->insert([
                        [ // id = 1
                            'personen_id' => $personId,
                            'standort_id' => $standortId,
                        ],
                    ]);
                }
            }
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

        $permissionCategories = $this->permissionCategoryCatalog();

        if (! DB::table('berechtigungskategories')->exists()) {
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
            [
                // id = 28
                'name' => 'Lager',
                'beschreibung' => 'Interne Lagerverwaltung fuer Verbrauchsmaterial und Betriebsmittel.',
            ],
            [
                // id = 29
                'name' => 'IT-Service',
                'beschreibung' => 'Helpdesk, IT-Tickets und IT-Geraeteverwaltung.',
            ],

            ]);
        }

        foreach ($permissionCategories as $category) {
            DB::table('berechtigungskategories')->updateOrInsert(
                ['name' => $category['name']],
                ['beschreibung' => $category['beschreibung']]
            );
        }

        $this->permissionCategoryIds = $this->loadPermissionCategoryIds($permissionCategories);

        $roles = [
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
            ],
            [
                'name' => 'IT',
                'guard_name' => 'web',
                'color' => 'bg-sky-300',
            ]

        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                [
                    'name' => $role['name'],
                    'guard_name' => $role['guard_name'],
                ],
                [
                    'color' => $role['color'],
                ]
            );
        }

        $permissions = $this->permissionCatalog();

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $permission['name'],
                    'guard_name' => $permission['guard_name'],
                ],
                [
                    'berechtigungskategorie_id' => $permission['berechtigungskategorie_id'],
                    'beschreibung' => $permission['beschreibung'],
                ]
            );
        }

        $userRoleAssignments = [
            'amin.masri@outlook.com' => $roles[0]['name'],
            'a.feller@zbb-saar.de' => $roles[4]['name'],
            's.gucciardo@zbb-saar.de' => $roles[2]['name'],
            'b.lautenschlager@zbb-saar.de' => $roles[1]['name'],
            'c.lismann@zbb-saar.de' => $roles[1]['name'],
        ];

        foreach ($userRoleAssignments as $email => $roleName) {
            $userId = DB::table('users')->where('email', $email)->value('id');
            $roleId = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if ($userId && $roleId) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $roleId,
                    'model_type' => 'App\Models\User',
                    'model_id' => $userId,
                ]);
            }
        }

        $administratorRoleId = DB::table('roles')
            ->where('name', $roles[0]['name'])
            ->where('guard_name', 'web')
            ->value('id');

        if ($administratorRoleId) {
            $administratorPermissionRows = DB::table('permissions')
                ->where('guard_name', 'web')
                ->pluck('id')
                ->map(fn ($permissionId) => [
                    'permission_id' => $permissionId,
                    'role_id' => $administratorRoleId,
                ])
                ->all();

            if ($administratorPermissionRows !== []) {
                DB::table('role_has_permissions')->insertOrIgnore($administratorPermissionRows);
            }

            $categoryIds = DB::table('berechtigungskategories')->pluck('id');

            foreach ($categoryIds as $categoryId) {
                $exists = DB::table('role_berechtigungskategories')
                    ->where('role_id', $administratorRoleId)
                    ->where('berechtigungskategorie_id', $categoryId)
                    ->exists();

                if (! $exists) {
                    DB::table('role_berechtigungskategories')->insert([
                        'role_id' => $administratorRoleId,
                        'berechtigungskategorie_id' => $categoryId,
                    ]);
                }
            }
        }

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
            'dienstwagen.buchungen.index',
            'dienstwagen.buchungen.store',
            'dienstwagen.buchungen.update',
            'dienstwagen.buchungen.destroy',
            'dienstwagen.meldungen.index',
            'dienstwagen.meldungen.store',
            'dienstwagen.meldungen.update',
            'dienstwagen.meldungen.destroy',
            'dienstwagen.verlauf.index',
            'dienstwagen.kosten.update',
            'dienstwagen.kosten.destroy',
            'lager.index',
            'lager.artikel.store',
            'lager.artikel.update',
            'lager.artikel.destroy',
            'lager.bewegung.store',
            'lager.reservierung.store',
            'lager.reservierung.update',
            'it.service.index',
            'it.ticket.store',
            'it.ticket.update',
            'it.ticket.destroy',
            'it.geraet.store',
            'it.geraet.update',
            'it.geraet.destroy',
        ];

        $extraPermissionIds = DB::table('permissions')
            ->whereIn('name', $extraPermissionNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        $extraRoleIds = DB::table('roles')
            ->whereIn('name', ['Administrator', 'Developer', 'IT'])
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

    public function permissionCategoryCatalog(): array
    {
        return [
            1 => ['name' => 'Dashboard', 'beschreibung' => 'Zentrale Uebersichten, Navigation und globale Einstiegspunkte.'],
            2 => ['name' => 'Kooperationspartner', 'beschreibung' => 'Schulen, Betriebe und weitere externe Partner.'],
            3 => ['name' => 'Gruppe', 'beschreibung' => 'Gruppen, Klassenbuch und gruppenbezogene Exporte.'],
            4 => ['name' => 'Bereich', 'beschreibung' => 'Bereiche und fachliche Bereichszuordnungen.'],
            5 => ['name' => 'Teilnehmer', 'beschreibung' => 'Teilnehmerdaten, Teilnehmerprofile und personenbezogene Teilnehmerfunktionen.'],
            6 => ['name' => 'TLN-GRP', 'beschreibung' => 'Zuordnung von Teilnehmern und Personen zu Gruppen.'],
            7 => ['name' => 'Rolle', 'beschreibung' => 'Rollenanlage und Rollenverwaltung.'],
            8 => ['name' => 'Permission', 'beschreibung' => 'Berechtigungsverwaltung, Rollenzuweisungen und Datenzugriff.'],
            9 => ['name' => 'Benutzer', 'beschreibung' => 'Benutzerkonten, Benutzerprofile und Sitzungsfunktionen.'],
            10 => ['name' => 'Auswertung', 'beschreibung' => 'Auswertungen, Berichte und finanzbezogene Einstiege.'],
            11 => ['name' => 'Anwesenheitsliste', 'beschreibung' => 'Anwesenheitserfassung, Exporte, Archivierung und abrechnungsbezogene BOP-Anwesenheitsunterlagen.'],
            12 => ['name' => 'Einteilung', 'beschreibung' => 'Einteilungen, Gruppengenerierung und Rundenzuordnung.'],
            13 => ['name' => 'Bereichauswahl', 'beschreibung' => 'Bereichsauswahl und BOP-bezogene Bereichsentscheidungen.'],
            14 => ['name' => 'Dateimanager', 'beschreibung' => 'Dateien, Ordner, Downloads, Uploads und Freigaben.'],
            15 => ['name' => 'Kalender', 'beschreibung' => 'Kalender, Termine, Kalenderimporte und Kalenderexporte.'],
            16 => ['name' => 'Kontakte', 'beschreibung' => 'Kontakte und Kontaktverwaltung im Apps-Bereich.'],
            17 => ['name' => 'Taskmanager', 'beschreibung' => 'Aufgaben, Workflows und taskbezogene App-Funktionen.'],
            18 => ['name' => 'Abteilung', 'beschreibung' => 'Abteilungen, Leitungen und Abteilungszuordnungen.'],
            19 => ['name' => 'Projekt', 'beschreibung' => 'Projekte, Kostenstellen, Dokumentvorlagen und Projektkonfiguration.'],
            20 => ['name' => 'Geraet', 'beschreibung' => 'Geraete, Geraeteausgaben und Geraeterueckgaben.'],
            21 => ['name' => 'Standort', 'beschreibung' => 'Standorte und standortbezogene Stammdaten.'],
            22 => ['name' => 'Fahrkarten', 'beschreibung' => 'Fahrtarten, Fahrtkosten und Fahrtkostenabrechnung.'],
            23 => ['name' => 'Printing', 'beschreibung' => 'Druckauftraege und Printing-Funktionen.'],
            24 => ['name' => 'Räumlichkeiten', 'beschreibung' => 'Raeume, Raummeldungen und Raumbuchungen.'],
            25 => ['name' => 'Dienstwagen', 'beschreibung' => 'Dienstwagen, Buchungen, Meldungen, Kosten und Fahrtenbuch.'],
            26 => ['name' => 'Personal', 'beschreibung' => 'Mitarbeiter, Personalstammdaten und Personalverwaltung.'],
            27 => ['name' => 'Bestellungen', 'beschreibung' => 'Materialanforderungen, Freigaben und Bestellwesen.'],
            28 => ['name' => 'Lager', 'beschreibung' => 'Interne Lagerverwaltung fuer Verbrauchsmaterial und Betriebsmittel.'],
            29 => ['name' => 'IT-Service', 'beschreibung' => 'Helpdesk, IT-Tickets und IT-Geraeteverwaltung.'],
        ];
    }

    private function loadPermissionCategoryIds(array $categories): array
    {
        $names = array_column($categories, 'name');
        $idsByName = DB::table('berechtigungskategories')
            ->select('name', DB::raw('MAX(id) as id'))
            ->whereIn('name', $names)
            ->groupBy('name')
            ->pluck('id', 'name');

        $ids = [];

        foreach ($categories as $legacyId => $category) {
            if (! isset($idsByName[$category['name']])) {
                throw new \RuntimeException("Berechtigungskategorie [{$category['name']}] wurde nicht gefunden.");
            }

            $ids[(int) $legacyId] = (int) $idsByName[$category['name']];
        }

        return $ids;
    }

    public function permissionCatalog(): array
    {
        return [
            // Dashboard / Navigation
            $this->permission('dashboard.index', 1, 'Erlaubt den Zugriff auf das zentrale Dashboard mit persoenlichen Uebersichten, Kennzahlen und Navigationseinstiegen.'),
            $this->permission('organisation.index', 1, 'Erlaubt den Zugriff auf den Organisationsbereich als Einstieg fuer Partner, Standorte, Abteilungen, Projekte und organisatorische Stammdaten.'),
            $this->permission('ressourcen.index', 1, 'Erlaubt den Zugriff auf den Ressourcenbereich als Einstieg fuer Personal, Dienstwagen, Raeume, Geraete und weitere Betriebsmittel.'),
            $this->permission('finanzen.index', 10, 'Erlaubt den Zugriff auf den Finanzbereich als Einstieg fuer Fahrtarten, Fahrtkostensaetze, Abrechnungen und finanzbezogene Auswertungen.'),
            $this->permission('apps.index', 1, 'Erlaubt den Zugriff auf die interne Apps-Uebersicht mit Dateimanager, Kalender, Kontakten, Aufgaben und Popups.'),
            $this->permission('notifications.readAll', 1, 'Erlaubt das Markieren eigener Benachrichtigungen als gelesen. Diese Berechtigung betrifft keine fremden Benachrichtigungen.'),

            // Berechtigungen / Rollen
            $this->permission('berechtigung.index', 8, 'Erlaubt das Oeffnen der Berechtigungsverwaltung und das Einsehen von Rollen, Kategorien, Datenzugriffen und zugewiesenen Permissions.'),
            $this->permission('berechtigung.store', 8, 'Erlaubt das Anlegen neuer Berechtigungen oder Rollenbestandteile innerhalb der Berechtigungsverwaltung, sofern eine entsprechende Funktion bereitgestellt ist.'),
            $this->permission('berechtigung.update', 8, 'Erlaubt das Veraendern von Rollenberechtigungen, das Zuweisen oder Entfernen einzelner Permissions und das Bearbeiten rollenbezogener Zugriffseinstellungen.'),
            $this->permission('berechtigung.destroy', 8, 'Erlaubt das Entfernen von Berechtigungen oder Rollenbestandteilen, sofern eine entsprechende Verwaltungsfunktion bereitgestellt ist.'),
            $this->permission('berechtigung.zuweisen', 8, 'Erlaubt das konkrete Zuweisen und Entziehen einzelner Permissions an Rollen in der Rollen- und Berechtigungsmaske.'),
            $this->permission('rolle.store', 7, 'Erlaubt das Anlegen neuer Rollen, die anschliessend mit Berechtigungskategorien, Permissions und Datenzugriffen ausgestattet werden koennen.'),
            $this->permission('rolle.destroy', 7, 'Erlaubt das Loeschen bestehender Rollen. Diese Berechtigung sollte nur an Administratoren oder sehr eingeschraenkte Systemverantwortliche vergeben werden.'),
            $this->permission('rolle.data-access.update', 8, 'Erlaubt das Bearbeiten des rollenbezogenen Datenzugriffs, also welche Mitarbeiter- und Teilnehmerdaten eine Rolle grundsaetzlich sehen darf.'),
            $this->permission('notification-rules.index', 8, 'Erlaubt das Einsehen der Benachrichtigungsregeln und der verfuegbaren Ereignisse, Empfaenger und Kanaele.'),
            $this->permission('notification-rules.store', 8, 'Erlaubt das Anlegen neuer Benachrichtigungsregeln fuer konfigurierbare Ereignisse.'),
            $this->permission('notification-rules.update', 8, 'Erlaubt das Bearbeiten und Aktivieren oder Deaktivieren bestehender Benachrichtigungsregeln.'),
            $this->permission('notification-rules.destroy', 8, 'Erlaubt das Entfernen bestehender Benachrichtigungsregeln.'),

            // Benutzer / Personal
            $this->permission('benutzer.index', 9, 'Erlaubt das Einsehen der Benutzeruebersicht mit Benutzerkonten, Rollen, Projektzuweisungen und zugehoerigen Personendaten im erlaubten Datenbereich.'),
            $this->permission('benutzer.store', 9, 'Erlaubt das Anlegen neuer Benutzerkonten inklusive Personendaten, Rollen, Projektzuweisungen und Standortzuordnungen.'),
            $this->permission('benutzer.update', 9, 'Erlaubt das Bearbeiten bestehender Benutzerkonten, Rollen, Login-Daten, Projektzuweisungen und Standortzuordnungen.'),
            $this->permission('benutzer.destroy', 9, 'Erlaubt das Loeschen von Benutzerkonten. Diese Berechtigung sollte sehr restriktiv vergeben werden.'),
            $this->permission('user.profil', 9, 'Erlaubt das Einsehen eines Benutzerprofils mit Rollen und Projekt- bzw. Abteilungsbezug, soweit der Datenzugriff der Rolle dies zulaesst.'),
            $this->permission('user.check', 9, 'Erlaubt das Umschalten administrativer Benutzerstatus-Felder, die in der Benutzerverwaltung als Schnellaktion verwendet werden.'),
            $this->permission('projekt.switch', 9, 'Erlaubt einem Benutzer, innerhalb der eigenen zugewiesenen Projekte das aktive Projekt fuer die Sitzung zu wechseln.'),
            $this->permission('personal.index', 26, 'Erlaubt das Einsehen der Personaluebersicht mit aktiven Mitarbeitern, Rollen, Projekten, Abteilungen und Standorten im erlaubten Datenbereich.'),
            $this->permission('personal.store', 26, 'Erlaubt das Anlegen neuer Personaldatensaetze oder Mitarbeiterkonten im Personalbereich, sofern die Funktion genutzt wird.'),
            $this->permission('personal.edit', 26, 'Erlaubt das Oeffnen der Bearbeitungsansicht fuer Mitarbeiter- und Personaldaten.'),
            $this->permission('personal.update', 26, 'Erlaubt das Aktualisieren von Personaldaten, Benutzerinformationen, Rollen sowie Projekt- und Standortzuweisungen im Personalbereich.'),
            $this->permission('personal.destroy', 26, 'Erlaubt das Entfernen von Personaldatensaetzen, sofern eine Loeschfunktion im Personalbereich bereitgestellt ist.'),

            // Partner / Schulen
            $this->permission('schule.index', 2, 'Erlaubt den Zugriff auf die Schuluebersicht bzw. schulbezogene Organisationsdaten.'),
            $this->permission('kooperationspartner.index', 2, 'Erlaubt das Einsehen der Partner- und Kooperationspartneruebersicht inklusive Schulen, Betrieben und weiteren externen Stellen.'),
            $this->permission('kooperationspartner.store', 2, 'Erlaubt das Anlegen neuer Kooperationspartner mit Stammdaten, Ansprechpartnern und BOP-relevanten Zusatzdaten.'),
            $this->permission('kooperationspartner.update', 2, 'Erlaubt das Bearbeiten bestehender Kooperationspartner, Schul- oder Partnerdaten sowie deren Zusatzinformationen.'),
            $this->permission('kooperationspartner.destroy', 2, 'Erlaubt das Loeschen von Kooperationspartnern. Vor Vergabe sollte geprueft werden, ob historische Projekt- oder Teilnehmerbeziehungen betroffen sein koennen.'),

            // Standorte / Abteilungen / Bereiche / Projekte
            $this->permission('standort.index', 21, 'Erlaubt das Einsehen der Standortuebersicht inklusive zugeordneter Personen, Projekte und Standortinformationen.'),
            $this->permission('standort.store', 21, 'Erlaubt das Anlegen neuer Standorte.'),
            $this->permission('standort.update', 21, 'Erlaubt das Bearbeiten bestehender Standorte und ihrer Beschreibung.'),
            $this->permission('standort.destroy', 21, 'Erlaubt das Loeschen von Standorten. Diese Berechtigung sollte nur vergeben werden, wenn Abhaengigkeiten zu Personen und Projekten bekannt sind.'),
            $this->permission('abteilung.index', 18, 'Erlaubt das Einsehen der Abteilungsuebersicht inklusive Leitung, Assistenz und zugeordneten Personen.'),
            $this->permission('abteilung.store', 18, 'Erlaubt das Anlegen neuer Abteilungen inklusive Abteilungsleitung und Assistenzzuordnung.'),
            $this->permission('abteilung.update', 18, 'Erlaubt das Bearbeiten von Abteilungen, Leitungen und Assistenzzuordnungen.'),
            $this->permission('abteilung.destroy', 18, 'Erlaubt das Loeschen von Abteilungen. Diese Berechtigung sollte wegen Projekt- und Rollenbezug sehr sparsam vergeben werden.'),
            $this->permission('bereich.index', 4, 'Erlaubt das Einsehen der Bereichsuebersicht und bereichsbezogener Stammdaten.'),
            $this->permission('bereich.store', 4, 'Erlaubt das Anlegen neuer Bereiche.'),
            $this->permission('bereich.update', 4, 'Erlaubt das Bearbeiten bestehender Bereiche und ihrer Beschreibung.'),
            $this->permission('bereich.destroy', 4, 'Erlaubt das Loeschen von Bereichen. Vor Vergabe sollte geprueft werden, ob Projekte oder Gruppen davon abhaengen.'),
            $this->permission('projekt.index', 19, 'Erlaubt das Einsehen der Projektuebersicht mit Abteilung, Zeitraeumen, Bereichen, Kostenstellen und Dokumentzuordnungen.'),
            $this->permission('projekt.show', 19, 'Erlaubt das Oeffnen einer Projekt-Detailansicht mit zugeordneten Mitarbeitern, Bereichen, Kostenstellen und Dokumentvorlagen.'),
            $this->permission('projekt.store', 19, 'Erlaubt das Anlegen neuer Projekte inklusive Zeitraum, Abteilung, Bereichen, Kostenstellen und Klassenbuch-Einstellung.'),
            $this->permission('projekt.update', 19, 'Erlaubt das Bearbeiten bestehender Projekte inklusive Stammdaten, Zeitraeumen, Bereichen, Kostenstellen und Klassenbuch-Aktivierung.'),
            $this->permission('projekt.destroy', 19, 'Erlaubt das Loeschen von Projekten. Diese Berechtigung ist kritisch, weil Teilnehmer-, Gruppen-, Dokument- und Abrechnungsdaten betroffen sein koennen.'),
            $this->permission('projekt.dokumente.update', 19, 'Erlaubt das Bearbeiten der Dokument- und Exportvorlagen, die einem Projekt fuer Gruppenexporte oder Serienbriefe zugeordnet sind.'),
            $this->permission('projekt.mitarbeiter.view.all', 19, 'Erlaubt das Einsehen aller Mitarbeiter eines Projekts und wird auch genutzt, um projektweite Gruppen- oder Betreuerdaten sichtbar zu machen.'),
            $this->permission('bvb_reha.workspace.index', 19, 'Erlaubt den lesenden Zugriff auf den BvB-Reha-Arbeitsbereich und explizit typisierte BvB-Reha-Projekte.'),
            $this->permission('bvb_reha.participants.index', 19, 'Erlaubt das Einsehen der vorhandenen Projektteilnahmen in explizit typisierten BvB-Reha-Projekten.'),
            $this->permission('kostenstelle.index', 19, 'Erlaubt das Einsehen der Kostenstellenuebersicht.'),
            $this->permission('kostenstelle.store', 19, 'Erlaubt das Anlegen neuer Kostenstellen fuer Projekte, Bestellungen oder finanzbezogene Zuordnungen.'),

            // Dokumente / Vorlagen
            $this->permission('dokumente.index', 19, 'Erlaubt das Einsehen des Dokumentenmanagers fuer Exportvorlagen und projektbezogene Dokumentkategorien.'),
            $this->permission('dokumente.store', 19, 'Erlaubt das Hochladen oder Anlegen neuer Dokument- und Exportvorlagen.'),
            $this->permission('dokumente.update', 19, 'Erlaubt das Bearbeiten bestehender Dokumentvorlagen, Metadaten, Kategorien und Einsatzbereiche.'),
            $this->permission('dokumente.download', 19, 'Erlaubt das Herunterladen von Dokumentvorlagen aus dem Dokumentenmanager.'),
            $this->permission('dokumente.kategorien.store', 19, 'Erlaubt das Anlegen neuer Dokumentkategorien fuer die Strukturierung von Export- und Projektvorlagen.'),
            $this->permission('dokumente.projekt-kategorien.update', 19, 'Erlaubt das Bearbeiten der Dokumentkategorien, die einem Projekt zugeordnet sind.'),

            // Teilnehmer
            $this->permission('teilnehmer.index', 5, 'Erlaubt das Einsehen der Teilnehmeruebersicht innerhalb des durch Rolle und Datenzugriff erlaubten Teilnehmerbereichs.'),
            $this->permission('teilnehmer.projekt.index', 5, 'Erlaubt das Einsehen von Teilnehmerlisten gefiltert nach einem Projekt.'),
            $this->permission('teilnehmer.store', 5, 'Erlaubt das Anlegen neuer Teilnehmer inklusive Stammdaten und erster Zuordnungen.'),
            $this->permission('teilnehmer.import', 5, 'Erlaubt den Import von Teilnehmerdaten aus Dateien oder Sammelquellen.'),
            $this->permission('teilnehmer.update', 5, 'Erlaubt das Bearbeiten von Teilnehmerdaten inklusive Stammdaten, Sozialdaten, Projektzuordnungen, Kontakten, Adressen, Bankdaten und Zusatzinformationen.'),
            $this->permission('teilnehmer.data-request.manage', 5, 'Erlaubt das Pruefen, Bearbeiten und Abschliessen von Datenauskunftsanfragen eines Teilnehmers innerhalb des rollenbezogen erlaubten Projekt- und Teilnehmerbereichs.'),
            $this->permission('teilnehmer.destroy', 5, 'Erlaubt das Loeschen einzelner Teilnehmer. Diese Berechtigung ist wegen personenbezogener Daten besonders kritisch.'),
            $this->permission('teilnehmer.bulkDestroy', 5, 'Erlaubt das Loeschen mehrerer Teilnehmer in einer Sammelaktion. Diese Berechtigung sollte nur sehr eingeschraenkt vergeben werden.'),
            $this->permission('teilnehmer.view.all', 5, 'Erlaubt das Einsehen aller Teilnehmer unabhaengig von Projekt, Standort oder Abteilung, sofern keine zusaetzliche Fachlogik einschraenkt.'),
            $this->permission('teilnehmer.view.abteilung', 5, 'Erlaubt das Einsehen von Teilnehmern aus Projekten der eigenen oder zugeordneten Abteilung.'),
            $this->permission('teilnehmer.view.projekt', 5, 'Erlaubt das Einsehen von Teilnehmern, die den eigenen oder zugeordneten Projekten angehoeren.'),
            $this->permission('teilnehmer.view.standort', 5, 'Erlaubt das Einsehen von Teilnehmern an den eigenen oder zugeordneten Standorten.'),
            $this->permission('person.sozialdaten.update', 5, 'Erlaubt das Bearbeiten der Sozialdaten eines Teilnehmers. Diese Berechtigung betrifft besonders sensible personenbezogene Informationen.'),
            $this->permission('abschluss.store', 5, 'Erlaubt das Hinzufuegen von Abschluessen oder Qualifikationen zu einem Teilnehmerprofil.'),
            $this->permission('abschluss.destroy', 5, 'Erlaubt das Entfernen von Abschluessen oder Qualifikationen aus einem Teilnehmerprofil.'),
            $this->permission('teilnehmer.praktikum.store', 5, 'Erlaubt das Erfassen von Praktika, Bildungsmaßnahmen oder vergleichbaren Teilnehmer-Zusatzdaten.'),
            $this->permission('kontakt.store', 5, 'Erlaubt das Anlegen von Kontaktdaten zu einem Teilnehmer.'),
            $this->permission('kontakt.destroy', 5, 'Erlaubt das Entfernen von Kontaktdaten aus einem Teilnehmerprofil.'),
            $this->permission('adresse.store', 5, 'Erlaubt das Anlegen von Adressdaten zu einem Teilnehmer.'),
            $this->permission('adresse.destroy', 5, 'Erlaubt das Entfernen von Adressdaten aus einem Teilnehmerprofil.'),
            $this->permission('bank.store', 5, 'Erlaubt das Anlegen von Bankdaten zu einem Teilnehmer. Diese Berechtigung betrifft besonders schutzbeduerftige Daten.'),
            $this->permission('bank.destroy', 5, 'Erlaubt das Entfernen von Bankdaten aus einem Teilnehmerprofil.'),
            $this->permission('projekthasteilnehmer.store', 5, 'Erlaubt das Zuordnen eines Teilnehmers zu einem Projekt.'),
            $this->permission('projekthasteilnehmer.update', 5, 'Erlaubt das Bearbeiten einer bestehenden Teilnehmer-Projekt-Zuordnung.'),
            $this->permission('projekthasteilnehmer.luv.store', 5, 'Erlaubt das Anlegen von LuV-Daten zu einer Teilnehmer-Projekt-Zuordnung.'),
            $this->permission('projekthasteilnehmer.luv.update', 5, 'Erlaubt das Bearbeiten bestehender LuV-Daten eines Teilnehmers.'),
            $this->permission('projekthasteilnehmer.luv.destroy', 5, 'Erlaubt das Loeschen von LuV-Daten eines Teilnehmers.'),
            $this->permission('projekthasteilnehmer.luv.export', 5, 'Erlaubt den Export von LuV-Daten eines Teilnehmers.'),
            $this->permission('export.excel.esfStammblatt', 5, 'Erlaubt den Excel-Export eines ESF-Stammblatts fuer einen Teilnehmer und ein Projekt.'),
            $this->permission('notizen.store', 5, 'Erlaubt das Anlegen von Notizen zu Teilnehmern oder fachlichen Datensaetzen im jeweiligen Kontext.'),
            $this->permission('notizen.destroy', 5, 'Erlaubt das Loeschen von Notizen im jeweiligen Kontext.'),
            $this->permission('brief.store', 5, 'Erlaubt das Erstellen von Briefen oder Schreiben im Teilnehmer- bzw. Benutzerkontext.'),
            $this->permission('brief.share', 5, 'Erlaubt das Freigeben eines Briefs fuer andere Benutzer oder Empfaenger.'),
            $this->permission('brief.destroy', 5, 'Erlaubt das Loeschen eigener oder verwalteter Briefe.'),
            $this->permission('briefShared.destroy', 5, 'Erlaubt das Entfernen einer erhaltenen oder geteilten Brieffreigabe.'),

            // Gruppen / Klassenbuch
            $this->permission('gruppe.index', 3, 'Erlaubt das Einsehen der Gruppenuebersicht im aktiven Projekt. Ohne Zusatzrecht werden in der Fachlogik nur eigene Gruppen angezeigt.'),
            $this->permission('gruppe.store', 3, 'Erlaubt das Anlegen neuer Gruppen im aktiven Projekt inklusive Bereich, Betreuer, Zeitraum und Raum- oder Standortzuordnung.'),
            $this->permission('gruppe.update', 3, 'Erlaubt das Bearbeiten bestehender Gruppen, sofern die Fachlogik den Benutzer als zustaendig oder berechtigt einstuft.'),
            $this->permission('gruppe.destroy', 3, 'Erlaubt das Loeschen von Gruppen, sofern die Fachlogik den Benutzer als zustaendig oder berechtigt einstuft.'),
            $this->permission('gruppe.view.all', 3, 'Erlaubt das Einsehen aller Gruppen im aktiven Projekt, nicht nur eigener Gruppen.'),
            $this->permission('gruppeHasTeilnehmer.show', 6, 'Erlaubt das Einsehen der Teilnehmerzuordnung einer Gruppe.'),
            $this->permission('gruppeHasTeilnehmer.store', 6, 'Erlaubt das Hinzufuegen von Teilnehmern zu einer Gruppe.'),
            $this->permission('gruppeHasTeilnehmer.destroyTeilnehmer', 6, 'Erlaubt das Entfernen eines bestimmten Teilnehmers aus einer Gruppe.'),
            $this->permission('klassenbuch.index', 3, 'Erlaubt das Einsehen der Klassenbuchuebersicht fuer Gruppen im aktiven Projekt.'),
            $this->permission('klassenbuch.store', 3, 'Erlaubt das Anlegen eines Klassenbuchs fuer eine berechtigte Gruppe.'),
            $this->permission('klassenbuch.show', 3, 'Erlaubt das Einsehen eines Klassenbuchs inklusive Wochen, Teilnehmerliste und Statusinformationen.'),
            $this->permission('klassenbuch.woche.show', 3, 'Erlaubt das Einsehen einer einzelnen Klassenbuchwoche mit Eintraegen und Kommentaren.'),
            $this->permission('klassenbuch.eintrag.store', 3, 'Erlaubt das Anlegen oder Aktualisieren von Unterrichts- oder Wochen-Eintraegen in einer bearbeitbaren Klassenbuchwoche.'),
            $this->permission('klassenbuch.eintrag.destroy', 3, 'Erlaubt das Loeschen von Klassenbucheintraegen in einer bearbeitbaren Klassenbuchwoche.'),
            $this->permission('klassenbuch.woche.submit', 3, 'Erlaubt das Einreichen einer Klassenbuchwoche zur Pruefung.'),
            $this->permission('klassenbuch.woche.review', 3, 'Erlaubt das Pruefen, Freigeben, Sperren oder zur Korrektur Zurueckgeben einer eingereichten Klassenbuchwoche.'),
            $this->permission('klassenbuch.kommentar.store', 3, 'Erlaubt das Schreiben von Kommentaren in einer Klassenbuchwoche. Interne Kommentare bleiben zusaetzlich an Pruefrechte gekoppelt.'),
            $this->permission('klassenbuch.kommentar.update', 3, 'Erlaubt das Bearbeiten eigener Klassenbuchkommentare oder, bei Pruefrecht, das Bearbeiten von Pruefkommentaren.'),

            // Gruppen- und BOP-Exporte
            $this->permission('gruppe.export.serienbrief', 3, 'Erlaubt das Erzeugen eines Serienbriefs fuer eine Gruppe anhand einer freigegebenen Dokumentvorlage.'),
            $this->permission('gruppe.bop.export.namensschilder', 3, 'Erlaubt den BOP-Export von Namensschildern fuer eine Gruppe.'),
            $this->permission('gruppe.bop.export.hausordnung', 3, 'Erlaubt den BOP-Export der Hausordnung fuer eine Gruppe.'),
            $this->permission('gruppe.bop.export.berufsfelderprobung', 3, 'Erlaubt den BOP-Export von Unterlagen zur Berufsfelderprobung fuer eine Gruppe.'),
            $this->permission('gruppe.bop.export.auswertungsbogen-bop', 3, 'Erlaubt den BOP-Export von Auswertungsboegen fuer eine Gruppe.'),
            $this->permission('gruppe.bop.export.toilettennutzungsliste', 3, 'Erlaubt den BOP-Export der Toilettennutzungsliste fuer eine Gruppe.'),
            $this->permission('gruppe.bop.export.zertifikat-pobo', 3, 'Erlaubt den BOP-Export von POBO-Zertifikaten fuer eine Gruppe.'),
            $this->permission('gruppe.bop.export.teilnahme-pobo', 3, 'Erlaubt den BOP-Export von POBO-Teilnahmebescheinigungen fuer eine Gruppe.'),
            $this->permission('gruppe.bop.export.zertifikat-pa', 3, 'Erlaubt den BOP-Export von PA-Zertifikaten fuer eine Gruppe.'),
            $this->permission('gruppe.bop.export.teilnahme-pa', 3, 'Erlaubt den BOP-Export von PA-Teilnahmebescheinigungen fuer eine Gruppe.'),
            $this->permission('gruppe.bop.export.auswertungsbogen-pa', 3, 'Erlaubt den BOP-Export von PA-Auswertungsboegen fuer eine Gruppe.'),

            // Anwesenheit: fachliche Rechte statt technischer Einzelrouten
            $this->permission('anwesenheit.index', 11, 'Erlaubt das Einsehen von Anwesenheitseintraegen, Statuswerten, Soll- und Ist-Zeiten sowie Anwesenheitsauswertungen der Teilnehmer, die gemaess aktivem Projekt und rollenbezogenem Datenzugriff sichtbar sind.'),
            $this->permission('anwesenheit.manage', 11, 'Erlaubt das Erfassen und Bearbeiten von Anwesenheitseintraegen im aktiven Projekt. Dazu gehoeren Anwesenheitsstatus, geplante und tatsaechliche Zeiten sowie Bemerkungen; das endgueltige Loeschen ist nicht enthalten.'),
            $this->permission('anwesenheit.destroy', 11, 'Erlaubt das endgueltige Loeschen einzelner Anwesenheitseintraege innerhalb des aktiven Projekts und des fuer die Rolle erlaubten Teilnehmerbereichs.'),
            $this->permission('anwesenheit.export', 11, 'Erlaubt normale, nicht abrechnungsbezogene Anwesenheitslisten und Anwesenheitsauswertungen fuer erlaubte Gruppen, Teilnehmer und Projekte zu exportieren.'),
            $this->permission('anwesenheit.archiv', 11, 'Erlaubt das Verwalten der verbindlichen Anwesenheitsablage, insbesondere das Erzeugen von Archivordnern und das Ablegen signierter PA- oder BIBB-PDF-Anwesenheitslisten im aktiven Projekt.'),
            $this->permission('anwesenheit.abrechnung', 11, 'Erlaubt die fuer die BOP-Abrechnung bestimmten Anwesenheitsunterlagen zu oeffnen, vorzubereiten, als Entwurf zu bearbeiten und zu exportieren. Enthalten sind Vorbereitung PA, PA-Anwesenheitsliste, BIBB-Anwesenheitsliste, Rolltag, Anwesenheitsdaten und Anwesenheitsliste Rechnung; die Archivablage benoetigt zusaetzlich anwesenheit.archiv.'),
            $this->permission('teilnehmer.liste.export', 5, 'Erlaubt den Export einer schulweiten Teilnehmerliste mit personenbezogenen Stammdaten wie Vorname, Nachname, Geschlecht, Geburtsdatum und Klasse. Das Recht gilt allgemein und ist nicht an einen Projektnamen gebunden; exportiert werden dennoch ausschliesslich Teilnehmer der ausgewaehlten Schule innerhalb des aktiven Projekts, Schuljahrs und Teilabschnitts. Andere Teilnehmerdokumente, Auswertungen oder dauerhafte Dateiablagen sind nicht enthalten.'),
            $this->permission('teilnehmer.liste.schule', 5, 'Erlaubt das Oeffnen oder Erzeugen einer schulbezogenen Teilnehmerlistenansicht im BOP-Kontext des aktiven Projekts.'),
            $this->permission('dokumente.ansprechpartner.manage', 14, 'Erlaubt das Erzeugen und dauerhafte Ablegen der fuer schulische Ansprechpartner bestimmten Unterlagen. Enthalten sind die Liste fehlender Elterneinverstaendniserklaerungen, das Anlegen der vorgesehenen Ordnerstruktur sowie das Generieren von BO-Auswertungen und PA-Berichten in diese Ordner. Das Recht erlaubt damit Schreibzugriffe auf die serverseitige Dokumentablage, jedoch keine anderen Datei- oder Teilnehmeraenderungen. Verarbeitet werden nur Daten der ausgewaehlten Schule im aktiven Projekt, Schuljahr und Teilabschnitt.'),
            $this->permission('bereichsauswahl.index', 13, 'Erlaubt das Einsehen der Bereichswahlen, Zugangscodes und des Bearbeitungsstands der Teilnehmer fuer einen Partner, ein Schuljahr und einen Teilabschnitt.'),
            $this->permission('bereichsauswahl.store', 13, 'Erlaubt das erstmalige Erfassen einer Bereichsauswahl fuer einen Teilnehmer im erlaubten Datenbereich. Bereits vorhandene Wahlen duerfen damit nicht geaendert werden.'),
            $this->permission('bereichsauswahl.update', 13, 'Erlaubt das Bearbeiten und Korrigieren der einzelnen Bereichswahlen eines Teilnehmers. Die zentrale Zugangssteuerung und Anzahl der Wahlfelder sind nicht enthalten.'),
            $this->permission('bereichsauswahl.destroy', 13, 'Erlaubt das Zuruecksetzen oder endgueltige Loeschen einer bestehenden Bereichsauswahl eines Teilnehmers. Die Stammdaten des Teilnehmers bleiben davon unberuehrt.'),
            $this->permission('bereichsauswahl.planning', 13, 'Erlaubt die zentrale Planung der Bereichsauswahl: Teilnehmerzugang aktivieren oder deaktivieren und die Anzahl der sichtbaren Wahlfelder festlegen. Einzelne Teilnehmerwahlen werden weiterhin ueber bereichsauswahl.store oder bereichsauswahl.update gesteuert.'),
            $this->permission('einteilung.index', 12, 'Erlaubt das Einsehen vorhandener Einteilungen, Runden, Kapazitaeten und Zuordnungen fuer einen Partner, ein Schuljahr und einen Teilabschnitt.'),
            $this->permission('einteilung.store', 12, 'Erlaubt das manuelle Anlegen einer Einteilung sowie das automatische Berechnen und Speichern einer neuen Einteilung anhand vorhandener Bereichswahlen.'),
            $this->permission('einteilung.update', 12, 'Erlaubt das Bearbeiten bestehender Einteilungen und das manuelle Verschieben oder Neuzuordnen einzelner Teilnehmer zwischen Bereichen.'),
            $this->permission('einteilung.destroy', 12, 'Erlaubt das Zuruecksetzen oder Loeschen der Einteilungen eines Partner-, Schuljahr- und Teilabschnittskontexts. Bereits erzeugte Teilnehmerzuordnungen werden dabei synchron bereinigt.'),
            $this->permission('einteilung.export', 12, 'Erlaubt den Excel-Export einer Einteilung einschliesslich Runden, Bereiche und Teilnehmerzuordnungen. Das Recht erlaubt keine Aenderung der Einteilungsdaten.'),
            $this->permission('einteilung.planning', 12, 'Erlaubt die administrative Einteilungsplanung: Rundenzahl und Kapazitaetsparameter festlegen, komplette Runden tauschen und aus einer fertigen Einteilung automatisch echte Gruppen mit Zeit-, Raum- und Betreuerzuordnung generieren.'),

            // Auswertungen / Dokumentexporte
            $this->permission('export.info_teilnehmende', 10, 'Erlaubt den Word-Export der Teilnehmerinformation fuer einen Teilnehmer.'),
            $this->permission('export.bildungsvertrag_inteqra', 10, 'Erlaubt den Word-Export des Bildungsvertrags Inteqra fuer einen Teilnehmer.'),
            $this->permission('export.datenschutzhinweis_art13', 10, 'Erlaubt den Word-Export des Datenschutzhinweises nach Art. 13 DSGVO.'),
            $this->permission('export.einverstaendnis_datenschutz_esf', 10, 'Erlaubt den Word-Export der ESF-Datenschutz-Einverstaendniserklaerung.'),
            $this->permission('export.fehlzeitenkonzept', 10, 'Erlaubt den Word-Export des Fehlzeitenkonzepts.'),
            $this->permission('export.einverstaendnis_foto', 10, 'Erlaubt den Word-Export der Foto-Einverstaendniserklaerung.'),
            $this->permission('export.einverstaendnis_elternarbeit', 10, 'Erlaubt den Word-Export der Einverstaendniserklaerung zur Elternarbeit.'),
            $this->permission('export.edv_nutzungsvereinbarung', 10, 'Erlaubt den Word-Export der EDV-Nutzungsvereinbarung.'),
            $this->permission('export.hausordnung_v1', 10, 'Erlaubt den Word-Export der Hausordnungsvorlage fuer einen Teilnehmer.'),
            $this->permission('dokumente.schule.export', 10, 'Erlaubt den Export schulweiter Dokumente fuer alle zur ausgewaehlten Schule, zum Schuljahr und zum Teilabschnitt gehoerenden Teilnehmer. Enthalten sind Hausordnung, beide Varianten des PA-Auswertungsbogens, POBO-Zertifikate als Word und PDF sowie die POBO-Auswertung insgesamt oder nach Runde. Das Recht gilt projektartenuebergreifend und erlaubt nur das Erzeugen oder Herunterladen dieser Dokumente; eine dauerhafte Ablage auf dem Server und der Export der Teilnehmerliste sind nicht enthalten. Der Datenumfang bleibt durch das aktive Projekt und den rollenbezogenen Datenzugriff begrenzt.'),

            // Finanzen
            $this->permission('fahrtarten.index', 22, 'Erlaubt das Einsehen der Fahrtartenuebersicht.'),
            $this->permission('fahrtarten.store', 22, 'Erlaubt das Anlegen neuer Fahrtarten.'),
            $this->permission('fahrtarten.update', 22, 'Erlaubt das Bearbeiten bestehender Fahrtarten.'),
            $this->permission('fahrtarten.destroy', 22, 'Erlaubt das Loeschen von Fahrtarten.'),
            $this->permission('fahrtkosten.index', 22, 'Erlaubt das Einsehen der Fahrtkostensatz-Uebersicht.'),
            $this->permission('fahrtkosten.store', 22, 'Erlaubt das Anlegen neuer Fahrtkostensaetze.'),
            $this->permission('fahrtkosten.update', 22, 'Erlaubt das Bearbeiten bestehender Fahrtkostensaetze.'),
            $this->permission('fahrtkosten.destroy', 22, 'Erlaubt das Loeschen von Fahrtkostensaetzen.'),
            $this->permission('fahrtkostenAbrechnung.store', 22, 'Erlaubt das Anlegen einer Fahrtkostenabrechnung fuer Teilnehmerfahrten.'),
            $this->permission('fahrtkostenAbrechnung.destroy', 22, 'Erlaubt das Loeschen einer Fahrtkostenabrechnung.'),

            // Geraete
            $this->permission('geraet.index', 20, 'Erlaubt das Einsehen der Geraeteuebersicht.'),
            $this->permission('geraet.store', 20, 'Erlaubt das Anlegen neuer Geraete.'),
            $this->permission('geraet.update', 20, 'Erlaubt das Bearbeiten bestehender Geraete.'),
            $this->permission('geraet.destroy', 20, 'Erlaubt das Loeschen bestehender Geraete.'),
            $this->permission('geraet.delete', 20, 'Erlaubt das Loeschen eines Geraets ueber die bestehende Legacy-Route.'),
            $this->permission('geraet.import', 20, 'Erlaubt den Import von Geraetedaten aus einer Datei.'),
            $this->permission('geraet.index.ausleihende', 20, 'Erlaubt das Einsehen der Ausleihendenuebersicht fuer Geraete.'),
            $this->permission('index-ausleihende', 20, 'Legacy-Permission fuer die Ausleihendenuebersicht, die von der vorhandenen check.permission-Middleware referenziert wird.'),
            $this->permission('geraet.ausgabe.index', 20, 'Erlaubt das Einsehen der Geraeteausgaben.'),
            $this->permission('geraet.ausgabe.store', 20, 'Erlaubt das Anlegen einer neuen Geraeteausgabe.'),
            $this->permission('geraet.ausgabe.store.add', 20, 'Erlaubt das Hinzufuegen weiterer Geraete zu einer bestehenden Ausgabe.'),
            $this->permission('geraet.ausgabe.update', 20, 'Erlaubt das Bearbeiten einer Geraeteausgabe.'),
            $this->permission('geraet.ausgabe.destroy', 20, 'Erlaubt das Loeschen einer Geraeteausgabe.'),
            $this->permission('geraet.ausgabe.export.excel', 20, 'Erlaubt den Excel-Export einer Geraeteausgabe.'),
            $this->permission('ausgabe.view', 20, 'Erlaubt das Oeffnen der Detailansicht einer Geraeteausgabe.'),
            $this->permission('geraet.rueckgabe.index', 20, 'Erlaubt das Einsehen der Geraeterueckgaben.'),
            $this->permission('geraet.rueckgabe.store', 20, 'Erlaubt das Anlegen einer neuen Geraeterueckgabe.'),
            $this->permission('geraet.rueckgabe.store.add', 20, 'Erlaubt das Hinzufuegen weiterer Geraete zu einer bestehenden Rueckgabe.'),
            $this->permission('geraet.rueckgabe.update', 20, 'Erlaubt das Bearbeiten einer Geraeterueckgabe.'),
            $this->permission('geraet.rueckgabe.destroy', 20, 'Erlaubt das Loeschen einer Geraeterueckgabe.'),
            $this->permission('geraet.rueckgabe.export.excel', 20, 'Erlaubt den Excel-Export einer Geraeterueckgabe.'),
            $this->permission('geraet.rueckgabe.geraete', 20, 'Erlaubt das Laden der zu einer Rueckgabe gehoerenden Geraeteliste.'),
            $this->permission('rueckgabe.view', 20, 'Erlaubt das Oeffnen der Detailansicht einer Geraeterueckgabe.'),

            // IT-Service
            $this->permission('it.service.index', 29, 'Erlaubt das Einsehen des IT-Service-Dashboards mit Tickets und Geraeten aller Standorte.'),
            $this->permission('it.ticket.store', 29, 'Erlaubt das Anlegen neuer IT-Tickets fuer Standorte, Personen oder Geraete.'),
            $this->permission('it.ticket.update', 29, 'Erlaubt das Priorisieren, Planen, Zuweisen und Abschliessen von IT-Tickets.'),
            $this->permission('it.ticket.destroy', 29, 'Erlaubt das Loeschen von IT-Tickets.'),
            $this->permission('it.geraet.store', 29, 'Erlaubt das Anlegen neuer IT-Geraete im IT-Service.'),
            $this->permission('it.geraet.update', 29, 'Erlaubt das Bearbeiten von IT-Geraeten inklusive Standort, Verantwortlichkeit und Wartungsdaten.'),
            $this->permission('it.geraet.destroy', 29, 'Erlaubt das Loeschen oder Aussondern von IT-Geraeten.'),

            // Raeume
            $this->permission('raeumlichkeiten.index', 24, 'Erlaubt das Einsehen der Raumuebersicht inklusive Standorten, Unterraeumen, Standardbelegung und Raummeldungen.'),
            $this->permission('raeumlichkeiten.store', 24, 'Erlaubt das Anlegen neuer Raeume inklusive Standort, Raumtyp, Kapazitaet und Belegungsart.'),
            $this->permission('raeumlichkeiten.update', 24, 'Erlaubt das Bearbeiten bestehender Raeume, ihrer Belegung und organisatorischen Zuordnung.'),
            $this->permission('raeumlichkeiten.destroy', 24, 'Erlaubt das Loeschen von Raeumen. Diese Berechtigung sollte wegen Gruppen- und Projektbezug zurueckhaltend vergeben werden.'),
            $this->permission('raeumlichkeiten.meldung.store', 24, 'Erlaubt das Melden von Schaeden, Problemen oder Aufgaben zu einem Raum.'),
            $this->permission('raeumlichkeiten.meldung.update', 24, 'Erlaubt das Bearbeiten, Priorisieren oder Abschliessen bestehender Raummeldungen.'),
            $this->permission('raeumlichkeiten.buchung.store', 24, 'Erlaubt das Anlegen von Raumbuchungen, Wartungsfenstern oder Sperrzeiten.'),
            $this->permission('raeumlichkeiten.buchung.update', 24, 'Erlaubt das Bearbeiten bestehender Raumbuchungen, Wartungsfenster oder Sperrzeiten.'),
            $this->permission('raeumlichkeiten.buchung.destroy', 24, 'Erlaubt das Stornieren von Raumbuchungen, Wartungsfenstern oder Sperrzeiten.'),

            // Dienstwagen
            $this->permission('dienstwagen.index', 25, 'Erlaubt das Einsehen der Dienstwagenuebersicht.'),
            $this->permission('dienstwagen.create', 25, 'Erlaubt das Oeffnen der Erstellungsansicht fuer Dienstwagen.'),
            $this->permission('dienstwagen.store', 25, 'Erlaubt das Anlegen neuer Dienstwagen.'),
            $this->permission('dienstwagen.edit', 25, 'Erlaubt das Oeffnen der Bearbeitungsansicht eines Dienstwagens.'),
            $this->permission('dienstwagen.update', 25, 'Erlaubt das Bearbeiten bestehender Dienstwagen.'),
            $this->permission('dienstwagen.destroy', 25, 'Erlaubt das Loeschen von Dienstwagen.'),
            $this->permission('dienstwagen.drivers.index', 25, 'Erlaubt das Einsehen der Fahrer- bzw. Dienstwagenfahreruebersicht.'),
            $this->permission('dienstwagen.wartung.index', 25, 'Erlaubt das Einsehen der Wartungsuebersicht fuer Dienstwagen.'),
            $this->permission('dienstwagen.wartung.store', 25, 'Erlaubt das Anlegen neuer Wartungseintraege fuer Dienstwagen.'),
            $this->permission('dienstwagen.wartung.update', 25, 'Erlaubt das Bearbeiten bestehender Wartungseintraege.'),
            $this->permission('dienstwagen.wartung.destroy', 25, 'Erlaubt das Loeschen von Wartungseintraegen.'),
            $this->permission('dienstwagen.kosten.index', 25, 'Erlaubt das Einsehen der Dienstwagenkostenuebersicht.'),
            $this->permission('dienstwagen.kosten.store', 25, 'Erlaubt das Erfassen neuer Dienstwagenkosten.'),
            $this->permission('dienstwagen.kosten.update', 25, 'Erlaubt das Bearbeiten von Dienstwagenkosten.'),
            $this->permission('dienstwagen.kosten.destroy', 25, 'Erlaubt das Loeschen von Dienstwagenkosten.'),
            $this->permission('dienstwagen.reports.index', 25, 'Erlaubt das Einsehen von Dienstwagenberichten und Auswertungen.'),
            $this->permission('dienstwagen.buchungen.index', 25, 'Erlaubt das Einsehen von Dienstwagenbuchungen.'),
            $this->permission('dienstwagen.buchungen.store', 25, 'Erlaubt das Anlegen von Dienstwagenbuchungen.'),
            $this->permission('dienstwagen.buchungen.update', 25, 'Erlaubt das Bearbeiten von Dienstwagenbuchungen.'),
            $this->permission('dienstwagen.buchungen.destroy', 25, 'Erlaubt das Loeschen oder Stornieren von Dienstwagenbuchungen.'),
            $this->permission('dienstwagen.meldungen.index', 25, 'Erlaubt das Einsehen von Dienstwagenmeldungen.'),
            $this->permission('dienstwagen.meldungen.store', 25, 'Erlaubt das Erfassen von Schaeden, Reparaturen und Aufgaben an Dienstwagen.'),
            $this->permission('dienstwagen.meldungen.update', 25, 'Erlaubt das Bearbeiten und Abschliessen von Dienstwagenmeldungen.'),
            $this->permission('dienstwagen.meldungen.destroy', 25, 'Erlaubt das Loeschen von Dienstwagenmeldungen.'),
            $this->permission('dienstwagen.verlauf.index', 25, 'Erlaubt das Einsehen des Dienstwagenverlaufs.'),
            $this->permission('dienstwagen.fahrtenbuch.index', 25, 'Erlaubt das Einsehen des Dienstwagen-Fahrtenbuchs entsprechend der zusaetzlichen Sichtrechte.'),
            $this->permission('dienstwagen.fahrtenbuch.store', 25, 'Erlaubt das Anlegen neuer Fahrtenbucheintraege.'),
            $this->permission('dienstwagen.fahrtenbuch.update', 25, 'Erlaubt das Bearbeiten bestehender Fahrtenbucheintraege.'),
            $this->permission('dienstwagen.fahrtenbuch.destroy', 25, 'Erlaubt das Loeschen von Fahrtenbucheintraegen.'),
            $this->permission('dienstwagen.fahrtenbuch.report', 25, 'Erlaubt das Erzeugen eines Fahrtenbuchberichts.'),
            $this->permission('dienstwagen.fahrtenbuch.report.pdf', 25, 'Erlaubt den PDF-Export eines Fahrtenbuchberichts.'),
            $this->permission('dienstwagen.fahrtenbuch.report.excel', 25, 'Erlaubt den Excel-Export eines Fahrtenbuchberichts.'),
            $this->permission('dienstwagen.fahrtenbuch.view.all', 25, 'Erlaubt das Einsehen aller Fahrtenbucheintraege unabhaengig von Projekt oder Abteilung.'),
            $this->permission('dienstwagen.fahrtenbuch.view.abteilung', 25, 'Erlaubt das Einsehen von Fahrtenbucheintraegen der eigenen oder zugeordneten Abteilung.'),
            $this->permission('dienstwagen.fahrtenbuch.view.projekt', 25, 'Erlaubt das Einsehen von Fahrtenbucheintraegen der eigenen oder zugeordneten Projekte.'),

            // Apps
            $this->permission('apps.files', 14, 'Erlaubt das Einsehen des Dateimanagers und der sichtbaren Dateien und Ordner.'),
            $this->permission('apps.files.folder.store', 14, 'Erlaubt das Anlegen neuer Ordner im Dateimanager.'),
            $this->permission('apps.files.upload', 14, 'Erlaubt das Hochladen neuer Dateien in den Dateimanager.'),
            $this->permission('apps.files.download', 14, 'Erlaubt das Herunterladen sichtbarer Dateien aus dem Dateimanager.'),
            $this->permission('apps.files.update', 14, 'Erlaubt das Umbenennen, Verschieben oder Bearbeiten von Metadaten eigener oder bearbeitbarer Dateien und Ordner.'),
            $this->permission('apps.files.destroy', 14, 'Erlaubt das Loeschen eigener oder bearbeitbarer Dateien und Ordner.'),
            $this->permission('apps.files.mail', 14, 'Erlaubt das Versenden einer Datei per E-Mail aus dem Dateimanager.'),
            $this->permission('apps.share', 14, 'Erlaubt das Freigeben eigener oder bearbeitbarer App-Objekte fuer Personen oder E-Mail-Empfaenger.'),
            $this->permission('apps.calendar', 15, 'Erlaubt das Einsehen des Kalenders und sichtbarer Kalenderereignisse.'),
            $this->permission('apps.calendar.events', 15, 'Erlaubt das Laden sichtbarer Kalenderereignisse fuer die Kalenderansicht.'),
            $this->permission('apps.calendar.export', 15, 'Erlaubt den Excel-Export sichtbarer Kalenderereignisse.'),
            $this->permission('apps.calendar.import.preview', 15, 'Erlaubt die Vorschau eines Kalenderimports aus einer Excel-Datei.'),
            $this->permission('apps.calendar.import.confirm', 15, 'Erlaubt das endgueltige Uebernehmen eines geprueften Kalenderimports.'),
            $this->permission('apps.calendar.calendars.store', 15, 'Erlaubt das Anlegen neuer Kalender.'),
            $this->permission('apps.calendar.styles.store', 15, 'Erlaubt das Speichern eigener Kalenderfarben und Darstellungsstile.'),
            $this->permission('apps.calendar.store', 15, 'Erlaubt das Anlegen neuer Kalenderereignisse.'),
            $this->permission('apps.calendar.move', 15, 'Erlaubt das Verschieben bearbeitbarer Kalenderereignisse.'),
            $this->permission('apps.calendar.copy', 15, 'Erlaubt das Kopieren bearbeitbarer Kalenderereignisse in andere Zeitraeume.'),
            $this->permission('apps.calendar.update', 15, 'Erlaubt das Bearbeiten eigener oder bearbeitbarer Kalenderereignisse.'),
            $this->permission('apps.calendar.destroy', 15, 'Erlaubt das Loeschen eigener oder bearbeitbarer Kalenderereignisse.'),
            $this->permission('apps.contacts', 16, 'Erlaubt das Einsehen sichtbarer Kontakte im Apps-Arbeitsbereich.'),
            $this->permission('apps.contacts.store', 16, 'Erlaubt das Anlegen neuer Kontakte.'),
            $this->permission('apps.contacts.update', 16, 'Erlaubt das Bearbeiten eigener oder bearbeitbarer Kontakte.'),
            $this->permission('apps.contacts.destroy', 16, 'Erlaubt das Loeschen eigener oder bearbeitbarer Kontakte.'),
            $this->permission('apps.tasks', 17, 'Erlaubt das Einsehen sichtbarer Aufgaben im Taskmanager.'),
            $this->permission('apps.tasks.store', 17, 'Erlaubt das Anlegen neuer Aufgaben.'),
            $this->permission('apps.tasks.update', 17, 'Erlaubt das Bearbeiten eigener, zugewiesener oder bearbeitbarer Aufgaben.'),
            $this->permission('apps.tasks.destroy', 17, 'Erlaubt das Loeschen eigener oder bearbeitbarer Aufgaben.'),
            $this->permission('apps.tasks.workflows.store', 17, 'Erlaubt das Anlegen neuer Workflow-Vorlagen fuer Aufgaben.'),
            $this->permission('apps.tasks.workflows.apply', 17, 'Erlaubt das Anwenden einer Workflow-Vorlage auf ein Projekt.'),
            $this->permission('apps.tasks.workflows.destroy', 17, 'Erlaubt das Deaktivieren oder Entfernen einer Workflow-Vorlage.'),
            $this->permission('apps.popups', 1, 'Erlaubt das Einsehen sichtbarer App-Popups und interner Mitteilungen.'),
            $this->permission('apps.popups.store', 1, 'Erlaubt das Anlegen neuer App-Popups fuer Benutzer, Teams oder Projekte.'),
            $this->permission('apps.popups.update', 1, 'Erlaubt das Bearbeiten eigener oder bearbeitbarer App-Popups.'),
            $this->permission('apps.popups.destroy', 1, 'Erlaubt das Loeschen eigener oder bearbeitbarer App-Popups.'),

            // Materialanforderungen / Bestellungen
            $this->permission('materialanforderung.index', 27, 'Erlaubt das Einsehen der Materialanforderungsuebersicht. Je nach Sonderrecht werden eigene, sachlich zu pruefende oder kaufmaennisch zu pruefende Anforderungen angezeigt.'),
            $this->permission('materialanforderung.create', 27, 'Erlaubt das Oeffnen der Erstellungsansicht fuer Materialanforderungen.'),
            $this->permission('materialanforderung.show', 27, 'Erlaubt das Einsehen der Detailansicht einer Materialanforderung, sofern eigene oder fachlich freigegebene Sichtrechte greifen.'),
            $this->permission('materialanforderung.store', 27, 'Erlaubt das Anlegen neuer Materialanforderungen inklusive Positionen, Kostenstelle und Projektbezug.'),
            $this->permission('materialanforderung.update', 27, 'Erlaubt das Bearbeiten eigener Materialanforderungen im Entwurfs- oder Rueckgabezustand.'),
            $this->permission('materialanforderung.destroy', 27, 'Erlaubt das Loeschen von Materialanforderungen. Diese Berechtigung sollte nur in klar geregelten Rollen vergeben werden.'),
            $this->permission('materialanforderung.genehmigen', 27, 'Erlaubt Statuswechsel im Genehmigungsprozess einer Materialanforderung, sofern die fachliche Sonderberechtigung dazu passt.'),
            $this->permission('materialanforderung.sachlich.genehmigen', 27, 'Erlaubt die sachliche Genehmigung einer Materialanforderung ueber die bestehende Genehmigungsroute.'),
            $this->permission('materialanforderung.sachlische_freigabe.index', 27, 'Erlaubt das Einsehen aller eingereichten Materialanforderungen, die im eigenen Projekt- oder Abteilungsbereich sachlich geprueft werden muessen.'),
            $this->permission('materialanforderung.sachlische_freigabe.show', 27, 'Erlaubt das Einsehen der Detaildaten einer Materialanforderung im Rahmen der sachlichen Pruefung.'),
            $this->permission('materialanforderung.sachlische_freigabe.update', 27, 'Erlaubt die sachliche Bearbeitung und Freigabe oder Rueckgabe einer Materialanforderung.'),
            $this->permission('materialanforderung.kaufmännische_freigabe.index', 27, 'Erlaubt das Einsehen aller sachlich freigegebenen Materialanforderungen zur kaufmaennischen Budget- und Bestellpruefung.'),
            $this->permission('materialanforderung.kaufmännische_freigabe.show', 27, 'Erlaubt das Einsehen der Detaildaten einer Materialanforderung im Rahmen der kaufmaennischen Pruefung.'),
            $this->permission('materialanforderung.kaufmännische_freigabe.update', 27, 'Erlaubt die kaufmaennische Bearbeitung, Freigabe oder Rueckgabe einer Materialanforderung.'),
            $this->permission('materialanforderung.bestellwesen.update', 27, 'Erlaubt die Bearbeitung im Bestellwesen, insbesondere Statuswechsel wie bestellt, geliefert oder teilweise geliefert.'),

            // Lager
            $this->permission('lager.index', 28, 'Erlaubt das Einsehen der internen Lageruebersicht inklusive Verfuegbarkeit und Reservierungen.'),
            $this->permission('lager.artikel.store', 28, 'Erlaubt das Anlegen neuer interner Lagerartikel.'),
            $this->permission('lager.artikel.update', 28, 'Erlaubt das Bearbeiten interner Lagerartikel und Stammdaten.'),
            $this->permission('lager.artikel.destroy', 28, 'Erlaubt das Deaktivieren oder Loeschen interner Lagerartikel.'),
            $this->permission('lager.bewegung.store', 28, 'Erlaubt das Buchen von Lagerbewegungen wie Eingang, Ausgang oder Korrektur.'),
            $this->permission('lager.reservierung.store', 28, 'Erlaubt das interne Reservieren von verfuegbaren Lagerartikeln.'),
            $this->permission('lager.reservierung.update', 28, 'Erlaubt das Ausgeben oder Stornieren interner Lagerreservierungen.'),

            // Printing
            $this->permission('printing.index', 23, 'Erlaubt das Einsehen des Druck- bzw. Printingbereichs.'),
            $this->permission('printing.store', 23, 'Erlaubt das Anlegen neuer Druckauftraege oder Druckkonfigurationen, sofern die Funktion bereitgestellt ist.'),
            $this->permission('printing.update', 23, 'Erlaubt das Bearbeiten bestehender Druckauftraege oder Druckkonfigurationen.'),
            $this->permission('printing.destroy', 23, 'Erlaubt das Loeschen von Druckauftraegen oder Druckkonfigurationen.'),
        ];
    }

    private function permission(string $name, int $categoryId, string $description): array
    {
        return [
            'name' => $name,
            'guard_name' => 'web',
            'berechtigungskategorie_id' => $this->permissionCategoryIds[$categoryId] ?? $categoryId,
            'beschreibung' => $description,
        ];
    }
}
