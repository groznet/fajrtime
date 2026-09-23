<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * High-latitude cities (Moscow, Kazan, Ufa...) are not supported in MVP:
     * in summer the sun does not reach the Fajr angle there.
     */
    public function run(): void
    {
        $cities = [
            ['Грозный', 43.318700, 45.698100, 'Europe/Moscow'],
            ['Махачкала', 42.984900, 47.504700, 'Europe/Moscow'],
            ['Назрань', 43.225700, 44.764500, 'Europe/Moscow'],
            ['Нальчик', 43.485300, 43.607100, 'Europe/Moscow'],
            ['Черкесск', 44.223300, 42.057800, 'Europe/Moscow'],
            ['Владикавказ', 43.020500, 44.681900, 'Europe/Moscow'],
            ['Астрахань', 46.347900, 48.033600, 'Europe/Astrakhan'],
        ];

        foreach ($cities as [$name, $latitude, $longitude, $timezone]) {
            City::updateOrCreate(['name' => $name], [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timezone' => $timezone,
                'calc_method' => 'russia',
                'asr_method' => 'shafii',
            ]);
        }
    }
}
