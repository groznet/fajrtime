<?php

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GroupService
{
    /**
     * @param  array{name: string, description?: ?string, stake_amount: int, cycle_days: int, visibility: string}  $data
     */
    public function create(User $creator, array $data): Group
    {
        return DB::transaction(function () use ($creator, $data) {
            $group = Group::create($data + [
                'creator_id' => $creator->id,
                'slug' => $this->uniqueSlug($data['name']),
                'invite_token' => Str::random(40),
            ]);

            $group->members()->attach($creator->id);

            return $group;
        });
    }

    public function join(User $user, Group $group): void
    {
        $group->members()->syncWithoutDetaching([$user->id]);
    }

    /**
     * The creator cannot leave, and nobody can leave while holding a stake in the group's open challenge.
     */
    public function leave(User $user, Group $group): void
    {
        if ($group->creator_id === $user->id) {
            throw ValidationException::withMessages(['group' => 'Создатель не может покинуть свою группу.']);
        }

        $inChallenge = $user->participations()
            ->where('status', 'active')
            ->whereHas('challenge', fn ($q) => $q->where('group_id', $group->id)->whereIn('status', \App\Models\Challenge::OPEN))
            ->exists();

        if ($inChallenge) {
            throw ValidationException::withMessages(['group' => 'Сначала выйдите из челленджа группы.']);
        }

        $group->members()->detach($user->id);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'group', 140, '');
        $slug = $base;

        while (Group::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
