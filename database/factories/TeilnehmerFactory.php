<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teilnehmer>
 */
class TeilnehmerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'vorname'    => $this->faker->firstName,
            'nachname'   => $this->faker->lastName,
            'geschlecht' => $this->faker->randomElement(['w', 'm', 'd']),
        ];
    }
}
