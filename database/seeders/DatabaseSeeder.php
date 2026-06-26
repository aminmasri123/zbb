<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\DienstwagenSeeder;
use Database\Seeders\DokumenteSeeder;
use Database\Seeders\TageSeeder;
use Database\Seeders\TeilnehmerSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {


       $this->call([
            UserSeeder::class,
            DienstwagenSeeder::class,
            DokumenteSeeder::class,
            TageSeeder::class,
            TeilnehmerSeeder::class,
        ]);
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
