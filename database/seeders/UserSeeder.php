<?php

namespace Database\Seeders;

use App\Models\Projekt;
use App\Models\ProjektHasTeilnehmer;
use App\Models\Teilnehmer;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        DB::table('users')->insert([
            [
                // id = 1
                'username' => 'Amin Masri',
                'first_name' => 'Amin',
                'last_name' => 'Masri',
                'email' => 'amin.masri@outlook.com',
                'password' => Hash::make('password'),
                'lang' => 'de',
            ],
            [   // id = 2
                'username' => 'Anika Feller',
                'first_name' => 'Anika',
                'last_name' => 'Feller',
                'email' => 'a.feller@zbb-saar.de',
                'password' => Hash::make('zbb.bop.hw'),
                'lang' => 'de',
            ],
            [ // id = 3
                'username' => 'Salvatore Gucciardo',
                'first_name' => 'Salvatore',
                'last_name' => 'Gucciardo',
                'email' => 's.gucciardo@zbb-saar.de',
                'password' => Hash::make('zbb.bop.ala'),
                'lang' => 'de',
            ],
            [ // id = 4
                'username' => 'Brigitta Lautenschlager',
                'first_name' => 'Birgitta',
                'last_name' => 'Lautenschlager',
                'email' => 'b.lautenschlager@zbb-saar.de',
                'password' => Hash::make('zbb.al'),
                'lang' => 'de',
            ],
            [ // id = 5
                'username' => 'Chantale Lismann',
                'first_name' => 'Chantale',
                'last_name' => 'Lismann',
                'email' => 'c.lismann@zbb-saar.de',
                'password' => Hash::make('zbb.al'),
                'lang' => 'de',
            ],
            [ // id = 6
                'username' => 'Stefanie Wagner',
                'first_name' => 'Stefanie',
                'last_name' => 'Wagner',
                'email' => 's.wagner@zbb-saar.de',
                'password' => Hash::make('zbb.al'),
                'lang' => 'de',
            ],
            [ // id = 7
                'username' => 'Stefan Haßdenteufel',
                'first_name' => 'Stefan',
                'last_name' => 'Haßdenteufel',
                'email' => 's.haßdenteufel@zbb-saar.de',
                'password' => Hash::make('zbb.al'),
                'lang' => 'de',
            ],
            [ // id = 8
                'username' => 'Martin Löw',
                'first_name' => 'Martin',
                'last_name' => 'Löw',
                'email' => 'm.loew@zbb-saar.de',
                'password' => Hash::make('zbb.al'),
                'lang' => 'de',
            ],

        ]);

        /*$faker = Faker::create();
        // Anzahl der Benutzer, die erstellt werden sollen
        $numberOfUsers = 50;
        for ($i = 0; $i < $numberOfUsers; $i++)
        {
            User::create([
                'username' => $faker->username,
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'email' => $faker->unique()->safeEmail,
                'password' => bcrypt('password'), // Standardpasswort
            ]);
        }*/

        DB::table('abteilungs')->insert([
            [ // id = 1
                'name' => 'Abt. Übergang Schule-Beruf',
                'user_id' => '4',
            ],
            [ // id = 2
                'name' => 'Abt. Aus- und Weiterbildung',
                'user_id' => '6',
            ],
            [ // id = 3
                'name' => 'Abt. Arbeit- und Lernen',
                'user_id' => '7',
            ],
            [ // id = 4
                'name' => 'Abt. Beratung, Integration & Vermittlung',
                'user_id' => '8',
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

        DB::table('projekts')->insert([
            [ // id = 1
                'name' => 'Inteqra',
                'kostenstelle' => '14462',
                'abteilung_id' => '1',
            ],
            [ // id = 2
                'name' => 'BvB Reha',
                'kostenstelle' => '14411',
                'abteilung_id' => '1',
            ],
            [ // id = 3
                'name' => 'Aques',
                'kostenstelle' => '14422',
                'abteilung_id' => '1',
            ],
            [ // id = 4
                'name' => 'Intqra PRO',
                'kostenstelle' => '14463',
                'abteilung_id' => '1',
            ],

            [ // id = 5
                'name' => 'Bop',
                'kostenstelle' => '14471',
                'abteilung_id' => '1',
            ],
            [ // id = 6
                'name' => 'Sofia',
                'kostenstelle' => '14488',
                'abteilung_id' => '1',
            ],
            [ // id = 7
                'name' => 'BIG Saar',
                'kostenstelle' => '14240',
                'abteilung_id' => '1',
            ],
            [ // id = 8
                'name' => 'Familien Info Saarbrücken',
                'kostenstelle' => '15830',
                'abteilung_id' => '1',
            ],
            [ // id = 9
                'name' => 'Kakadu',
                'kostenstelle' => '11700',
                'abteilung_id' => '1',
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
            [ // id = 1
                'name' => 'berechtigung.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '8',
            ],
            [ // id = 1
                'name' => 'berechtigung.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '8',
            ],

            [ // id = 2
                'name' => 'benutzer.index',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '9',

            ],
            [ // id = 3
                'name' => 'benutzer.store',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '9',

            ],
            [ // id = 4
                'name' => 'benutzer.update',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '9',

            ],
            [ // id = 5
                'name' => 'benutzer.destroy',
                'guard_name' => 'web',
                'berechtigungskategorie_id' => '9',

            ],

             [ // id = 2
                'name' => 'kooperationspartner.index',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',

            ],
            [ // id = 3
                'name' => 'kooperationspartner.store',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',

            ],
            [ // id = 4
                'name' => 'kooperationspartner.update',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',

            ],
            [ // id = 5
                'name' => 'kooperationspartner.destroy',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',
            ],

            [ // id = 5
                'name' => 'bereich.index',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',
            ],
            [ // id = 5
                'name' => 'bereich.store',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',
            ],
            [ // id = 5
                'name' => 'bereich.destroy',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '2',
            ],
            [ // id = 5
                'name' => 'projekt.index',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '19',
            ],
            [ // id = 5
                'name' => 'projekt.store',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '19',
            ],
            [ // id = 5
                'name' => 'projekt.destroy',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '19',
            ],
            [ // id = 5
                'name' => 'abteilung.index',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '18',
            ],
            [ // id = 5
                'name' => 'abteilung.store',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '18',
            ],
            [ // id = 5
                'name' => 'abteilung.destroy',
                'beschreibung' => 'web',
                'berechtigungskategorie_id' => '18',
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


        ]);

        DB::table('user_has_projekts')->insert([
            [
                'user_id' => '1',
                'projekt_id' => '1',
            ],
            [
                'user_id' => '1',
                'projekt_id' => '2',
            ],
            [
                'user_id' => '1',
                'projekt_id' => '3',
            ],

        ]);




        //Teilnehmer erstellen und mit Projekten verknüpfen
        $faker = Faker::create();
        // Anzahl der Benutzer, die erstellt werden sollen
        $numberOfUsers = 50;
        for ($i = 0; $i < $numberOfUsers; $i++)
        {
            $teilnehmer = Teilnehmer::create([
                'vorname' => $faker->username,
                'nachname' => $faker->firstName,
                'geschlecht' => $faker->randomElement(['m', 'd', 'w']),
            ]);

             // Projekt-IDs und Teilnehmer-IDs müssen aus DB kommen
            ProjektHasTeilnehmer::create([
                'projekt_id'    => $faker->randomElement(Projekt::pluck('id')->toArray()),
                'teilnehmer_id' => $teilnehmer->id, // gerade erstellter Teilnehmer
            ]);
        };
    }

}
