<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'creator_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->sentence(),
            'stake_amount' => 500_00,
            'cycle_days' => 7,
            'visibility' => 'public',
            'invite_token' => Str::random(40),
        ];
    }

    public function private(): static
    {
        return $this->state(['visibility' => 'private']);
    }
}
