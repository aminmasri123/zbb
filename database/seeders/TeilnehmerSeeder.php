<?php

namespace Database\Seeders;
use Faker\Factory as Faker;
use App\Models\Projekt;
use App\Models\ProjektHasTeilnehmer;
use App\Models\Teilnehmer;
use Illuminate\Database\Seeder;
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
