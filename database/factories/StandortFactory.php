<?php

namespace Database\Factories;

use App\Models\Standort;
use Illuminate\Database\Eloquent\Factories\Factory;

class StandortFactory extends Factory
{
    protected $model = Standort::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->city(),
            'beschreibung' => null,
        ];
    }
}
