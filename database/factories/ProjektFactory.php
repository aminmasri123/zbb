<?php

namespace Database\Factories;

use App\Models\Abteilung;
use App\Models\Projekt;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjektFactory extends Factory
{
    protected $model = Projekt::class;

    public function definition(): array
    {
        return [
            'name' => substr($this->faker->unique()->word(), 0, 30),
            'abteilung_id' => Abteilung::factory(),
            'beschreibung' => null,
            'aktiv' => true,
        ];
    }
}
