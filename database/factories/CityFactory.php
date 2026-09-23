<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'latitude' => 43.318700,
            'longitude' => 45.698100,
            'timezone' => 'Europe/Moscow',
            'calc_method' => 'russia',
            'asr_method' => 'shafii',
        ];
    }
}
