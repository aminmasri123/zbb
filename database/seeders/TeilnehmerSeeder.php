<?php

namespace Database\Seeders;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Teilnehmer;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use App\Models\ProjektHasPersonen;
use App\Models\ProjektHasTeilnehmer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class TeilnehmerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {


        //Teilnehmer erstellen und mit Projekten verknüpfen
        $faker = Faker::create();
        // Anzahl der Benutzer, die erstellt werden sollen
        $numberOfUsers = 50;
        for ($i = 0; $i < $numberOfUsers; $i++)
        {
            $teilnehmer = Personen::create([
                'vorname' => $faker->username,
                'nachname' => $faker->firstName,
                'geschlecht' => $faker->randomElement(['m', 'd', 'w']),
                'typ' => 'teilnehmer',
            ]);

             // Projekt-IDs und Teilnehmer-IDs müssen aus DB kommen
            ProjektHasPersonen::create([
                'projekt_id'    => $faker->randomElement(Projekt::pluck('id')->toArray()),
                'personen_id' => $teilnehmer->id, // gerade erstellter Teilnehmer
            ]);
        };
    }
}
