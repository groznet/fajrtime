<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(3)->startOfDay();

        return [
            'group_id' => Group::factory(),
            'stake_amount' => 500_00,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(6),
            'status' => 'recruiting',
        ];
    }

    public function status(string $status): static
    {
        return $this->state(['status' => $status]);
    }
}
