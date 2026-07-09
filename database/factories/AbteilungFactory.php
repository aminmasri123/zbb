<?php

namespace Database\Factories;

use App\Models\Abteilung;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbteilungFactory extends Factory
{
    protected $model = Abteilung::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'personen_id' => null,
        ];
    }
}
