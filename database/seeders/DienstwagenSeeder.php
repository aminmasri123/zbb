<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DienstwagenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         DB::table('dienstwagens')->insert([
            [
                'typ'          => 'PKW',
                'kennzeichen'  => 'AB-1234',
                'marke'        => 'Audi',
                'modell'       => 'Q5',
                'baujahr'      => '2022',
                'kraftstoffart'=> 'Diesel',
                'kilometerstand'=> '15000',
                'standort_id'  => 1,
                'status'       => 'verfügbar',
                'naechste_wartung'=> '2025-10-17',
            ],
            [
                'typ'          => 'PKW',
                'kennzeichen'  => 'BC-5678',
                'marke'        => 'BMW',
                'modell'       => 'X3',
                'baujahr'      => '2021',
                'kraftstoffart'=> 'Benzin',
                'kilometerstand'=> '32000',
                'standort_id'  => 1,
                'status'       => 'verfügbar',
                'naechste_wartung'=> '2025-08-12',
            ],
            [
                'typ'          => 'Transporter',
                'kennzeichen'  => 'DE-9012',
                'marke'        => 'Mercedes',
                'modell'       => 'Sprinter',
                'baujahr'      => '2020',
                'kraftstoffart'=> 'Diesel',
                'kilometerstand'=> '87000',
                'standort_id'  => 2,
                'status'       => 'verfügbar',
                'naechste_wartung'=> '2025-07-30',
            ],
            [
                'typ'          => 'PKW',
                'kennzeichen'  => 'FG-3456',
                'marke'        => 'Volkswagen',
                'modell'       => 'Golf',
                'baujahr'      => '2023',
                'kraftstoffart'=> 'Hybrid',
                'kilometerstand'=> '8000',
                'standort_id'  => 2,
                'status'       => 'verfügbar',
                'naechste_wartung'=> '2026-01-05',
            ],
            [
                'typ'          => 'PKW',
                'kennzeichen'  => 'HI-7890',
                'marke'        => 'Tesla',
                'modell'       => 'Model 3',
                'baujahr'      => '2024',
                'kraftstoffart'=> 'Elektro',
                'kilometerstand'=> '5000',
                'standort_id'  => 3,
                'status'       => 'verfügbar',
                'naechste_wartung'=> '2026-03-11',
            ],
            [
                'typ'          => 'Transporter',
                'kennzeichen'  => 'JK-1122',
                'marke'        => 'Ford',
                'modell'       => 'Transit',
                'baujahr'      => '2019',
                'kraftstoffart'=> 'Diesel',
                'kilometerstand'=> '122000',
                'standort_id'  => 3,
                'status'       => 'verfügbar',
                'naechste_wartung'=> '2025-05-09',
            ],
            [
                'typ'          => 'PKW',
                'kennzeichen'  => 'LM-3344',
                'marke'        => 'Skoda',
                'modell'       => 'Octavia',
                'baujahr'      => '2022',
                'kraftstoffart'=> 'Diesel',
                'kilometerstand'=> '29000',
                'standort_id'  => 1,
                'status'       => 'verfügbar',
                'naechste_wartung'=> '2025-09-14',
            ],
            [
                'typ'          => 'PKW',
                'kennzeichen'  => 'NO-5566',
                'marke'        => 'Opel',
                'modell'       => 'Astra',
                'baujahr'      => '2020',
                'kraftstoffart'=> 'Benzin',
                'kilometerstand'=> '68000',
                'standort_id'  => 2,
                'status'       => 'verfügbar',
                'naechste_wartung'=> '2025-06-20',
            ],
            [
                'typ'          => 'Transporter',
                'kennzeichen'  => 'PQ-7788',
                'marke'        => 'Renault',
                'modell'       => 'Master',
                'baujahr'      => '2021',
                'kraftstoffart'=> 'Diesel',
                'kilometerstand'=> '54000',
                'standort_id'  => 3,
                'status'       => 'verfügbar',
                'naechste_wartung'=> '2025-12-02',
            ],
            [
                'typ'          => 'PKW',
                'kennzeichen'  => 'RS-9900',
                'marke'        => 'Toyota',
                'modell'       => 'Corolla',
                'baujahr'      => '2023',
                'kraftstoffart'=> 'Hybrid',
                'kilometerstand'=> '12000',
                'standort_id'  => 2,
                'status'       => 'verfügbar',
                'naechste_wartung'=> '2026-02-18',
            ],
        ]);


/*
        $faker = Faker::create('de_DE'); // deutsche Kennzeichen

        for ($i = 0; $i < 10; $i++) {
            DB::table('dienstwagens')->insert([
                'typ'             => $faker->randomElement(['PKW', 'Transporter', 'SUV']),
                'kennzeichen'     => strtoupper($faker->bothify('??-####')),
                'marke'           => $faker->randomElement(['Audi', 'BMW', 'Mercedes', 'Volkswagen', 'Ford', 'Opel', 'Renault', 'Skoda']),
                'modell'          => $faker->randomElement(['A3', 'A4', 'Q5', 'Golf', 'X3', 'Insignia', 'Transit', 'Master']),
                'baujahr'         => $faker->year(),
                'kraftstoffart'   => $faker->randomElement(['Benzin', 'Diesel', 'Hybrid', 'Elektro']),
                'kilometerstand'  => $faker->numberBetween(0, 200000),
                'standort_id'     => $faker->numberBetween(1, 3),
                'status'          => $faker->randomElement(['verfügbar', 'verliehen', 'in Wartung', 'defekt']),
                'naechste_wartung'=> $faker->date(),
            ]);
        }
 */
    }
}
