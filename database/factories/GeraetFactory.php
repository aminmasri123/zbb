<?php

namespace Database\Factories;

use App\Models\Geraet;
use Illuminate\Database\Eloquent\Factories\Factory;

class GeraetFactory extends Factory
{
    protected $model = Geraet::class;

    public function definition(): array
    {
        return [
            'sn' => strtoupper($this->faker->unique()->bothify('SN####??')),
            'productID' => strtoupper($this->faker->unique()->bothify('PID####')),
            'zustand' => 'gut',
            'geraet' => 'Laptop',
            'imLager' => null,
            'hersteller' => 'Test',
            'modell' => 'Modell',
            'baujahr' => null,
            'garantiefrist' => null,
            'verfuegbarkeit' => true,
        ];
    }
}
