<?php

namespace Database\Seeders;

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
        Teilnehmer::factory()->count(200)->create();
    }
}
