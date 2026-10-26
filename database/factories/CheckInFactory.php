<?php

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    public function definition(): array
    {
        $fajr = now()->addHours(2);

        return [
            'user_id' => User::factory(),
            'date' => $fajr->toDateString(),
            'fajr_at' => $fajr,
            'code' => '123456',
            'reveal_at' => $fajr->copy()->addMinutes(5),
            'expires_at' => $fajr->copy()->addMinutes(10),
            'attempts' => 0,
            'status' => 'pending',
        ];
    }
}
