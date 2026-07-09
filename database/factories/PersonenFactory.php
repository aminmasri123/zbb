<?php

namespace Database\Factories;

use App\Models\Personen;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonenFactory extends Factory
{
    protected $model = Personen::class;

    public function definition(): array
    {
        return [
            'vorname' => $this->faker->firstName(),
            'nachname' => $this->faker->lastName(),
            'geschlecht' => $this->faker->randomElement(['w', 'm', 'd']),
            'geburtsdatum' => null,
            'aktiv' => true,
            'typ' => 'mitarbeiter',
        ];
    }
}
