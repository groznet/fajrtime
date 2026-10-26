<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\Group;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChallengeService
{
    public function __construct(private WalletService $wallet) {}

    /**
     * Today's date (Y-m-d) in the challenge timezone. Recruiting closes when this reaches `start_date`.
     * Dates are compared as strings because `start_date` is a plain date, not a moment in time.
     */
    public function today(): string
    {
        return CarbonImmutable::now(config('fajr.challenge_timezone'))->toDateString();
    }

    /**
     * Open a recruiting challenge for the group. Stake and length are frozen from the group's current rules.
     */
    public function create(Group $group, CarbonInterface $startDate): Challenge
    {
        return DB::transaction(function () use ($group, $startDate) {
            // Serialise concurrent attempts to open two challenges for one group.
            Group::whereKey($group->id)->lockForUpdate()->firstOrFail();

            if ($group->challenges()->whereIn('status', Challenge::OPEN)->exists()) {
                throw ValidationException::withMessages(['start_date' => 'У группы уже есть челлендж, который идёт или набирает участников.']);
            }

            $start = CarbonImmutable::parse($startDate->toDateString());

            if ($start->toDateString() <= $this->today()) {
                throw ValidationException::withMessages(['start_date' => 'Дата старта должна быть не раньше завтрашнего дня.']);
            }

            return $group->challenges()->create([
                'stake_amount' => $group->stake_amount,
                'start_date' => $start,
                'end_date' => $start->addDays($group->cycle_days - 1),
                'status' => 'recruiting',
            ]);
        });
    }

    /**
     * Join a recruiting challenge: checks the rules and locks the stake.
     */
    public function join(User $user, Challenge $challenge): ChallengeParticipant
    {
        return DB::transaction(function () use ($user, $challenge) {
            $challenge = Challenge::whereKey($challenge->id)->lockForUpdate()->firstOrFail();

            if (! $challenge->isRecruiting() || $challenge->start_date->toDateString() <= $this->today()) {
                throw ValidationException::withMessages(['challenge' => 'Набор в этот челлендж закрыт.']);
            }

            if (! $challenge->group->hasMember($user)) {
                throw ValidationException::withMessages(['challenge' => 'Сначала вступите в группу.']);
            }

            $participant = $challenge->participants()->where('user_id', $user->id)->first();

            if ($participant?->status === 'active') {
                throw ValidationException::withMessages(['challenge' => 'Вы уже участвуете в этом челлендже.']);
            }

            // MVP rule: one recruiting or running challenge at a time.
            if ($user->hasOpenChallenges()) {
                throw ValidationException::withMessages(['challenge' => 'Вы уже участвуете в другом челлендже. Вступить в новый можно после его завершения.']);
            }

            if ($challenge->participants()->where('status', 'active')->count() >= Setting::int('max_participants')) {
                throw ValidationException::withMessages(['challenge' => 'В челлендже нет свободных мест.']);
            }

            $this->wallet->lockStake($user, $challenge);

            if ($participant) {
                $participant->update(['status' => 'active']);

                return $participant;
            }

            return $challenge->participants()->create(['user_id' => $user->id, 'status' => 'active']);
        });
    }

    /**
     * Leave a challenge that is still recruiting; the stake is unlocked.
     * Leaving a running challenge is not possible (it would be an elimination).
     */
    public function leave(User $user, Challenge $challenge): void
    {
        DB::transaction(function () use ($user, $challenge) {
            $challenge = Challenge::whereKey($challenge->id)->lockForUpdate()->firstOrFail();

            if (! $challenge->isRecruiting()) {
                throw ValidationException::withMessages(['challenge' => 'Выйти можно только до старта челленджа.']);
            }

            $participant = $challenge->participants()->where('user_id', $user->id)->where('status', 'active')->first();

            if (! $participant) {
                throw ValidationException::withMessages(['challenge' => 'Вы не участвуете в этом челлендже.']);
            }

            $participant->update(['status' => 'left']);
            $this->wallet->unlockStake($user, $challenge);
        });
    }

    /**
     * Start (or cancel, if there are too few participants) every recruiting challenge whose start date has come.
     * Safe to run repeatedly.
     *
     * @return int number of challenges processed
     */
    public function startDue(): int
    {
        $ids = Challenge::where('status', 'recruiting')->where('start_date', '<=', $this->today())->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id) {
                $challenge = Challenge::whereKey($id)->lockForUpdate()->first();

                if ($challenge?->status !== 'recruiting') {
                    return;
                }

                $enough = $challenge->participants()->where('status', 'active')->count() >= Setting::int('min_participants');

                $enough ? $challenge->update(['status' => 'active']) : $this->cancel($challenge);
            });
        }

        return $ids->count();
    }

    /**
     * Cancel a challenge and give every stake back. Must run inside a DB transaction.
     */
    private function cancel(Challenge $challenge): void
    {
        $challenge->participants()->where('status', 'active')->with('user')->get()->each(function (ChallengeParticipant $participant) use ($challenge) {
            $participant->update(['status' => 'left']);
            $this->wallet->unlockStake($participant->user, $challenge);
        });

        $challenge->update(['status' => 'cancelled']);
    }
}
