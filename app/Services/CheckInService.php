<?php

namespace App\Services;

use App\Enums\CheckInResult;
use App\Models\CheckIn;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ParticipantEliminated;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily Fajr check-in: random reveal time, one-time code, validation and elimination.
 *
 * The code and its reveal time live in the database from the moment the row is scheduled,
 * but state() hands the code to the browser only after reveal_at has passed.
 */
class CheckInService
{
    public function __construct(private PrayerTimeService $prayerTimes) {}

    /**
     * Create today's/tomorrow's check-in rows for everyone with a running challenge whose Fajr is
     * coming up soon. Idempotent: safe to run every few minutes.
     *
     * @return int number of rows created
     */
    public function scheduleDue(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $created = 0;

        $users = User::query()
            ->whereNotNull('city_id')
            ->whereHas('participations', fn ($q) => $q->where('status', 'active')
                ->whereHas('challenge', fn ($c) => $c->where('status', 'active')))
            ->with('city')
            ->cursor();

        foreach ($users as $user) {
            foreach ([0, 1] as $offset) {
                $date = $now->setTimezone($user->timezone)->addDays($offset)->toDateString();

                if ($this->scheduleFor($user, $date, $now)) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * The user's check-in the page should show: today's, or else the next upcoming one.
     */
    public function current(User $user, ?CarbonImmutable $now = null): ?CheckIn
    {
        $today = ($now ?? CarbonImmutable::now())->setTimezone($user->timezone)->toDateString();

        return CheckIn::where('user_id', $user->id)->where('date', '>=', $today)->orderBy('date')->first();
    }

    /**
     * What the browser may know right now. The code and reveal time are included only once
     * the code has been revealed and is still valid.
     */
    public function state(User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $checkIn = $this->current($user, $now);

        $state = [
            'status' => 'none',
            'server_now' => $now->toIso8601String(),
        ];

        if ($checkIn === null) {
            return $state;
        }

        $from = $this->rangeStart($checkIn);
        $to = $from->addMinutes(Setting::int('reveal_range_length_min'));
        $remaining = max(0, Setting::int('checkin_max_attempts') - $checkIn->attempts);

        $state += [
            'date' => $checkIn->date->toDateString(),
            'range_from' => $from->setTimezone($user->timezone)->format('H:i'),
            'range_to' => $to->setTimezone($user->timezone)->format('H:i'),
            'attempts_left' => $remaining,
        ];

        if ($checkIn->status === 'confirmed') {
            return ['status' => 'confirmed'] + $state;
        }

        if ($checkIn->status === 'missed' || $now->gte($checkIn->expires_at)) {
            return ['status' => 'missed'] + $state;
        }

        if ($now->lt($checkIn->reveal_at)) {
            return ['status' => 'waiting'] + $state;
        }

        if ($remaining === 0) {
            return ['status' => 'locked'] + $state;
        }

        return [
            'status' => 'revealed',
            'code' => $checkIn->code,
            'expires_at' => $checkIn->expires_at->toIso8601String(),
        ] + $state;
    }

    /**
     * Check a code typed by the user. Wrong codes and attempts before the reveal both count against the daily limit.
     */
    public function submit(User $user, string $input, ?CarbonImmutable $now = null): CheckInResult
    {
        $now ??= CarbonImmutable::now();

        return DB::transaction(function () use ($user, $input, $now) {
            $current = $this->current($user, $now);

            if ($current === null) {
                return CheckInResult::None;
            }

            $checkIn = CheckIn::whereKey($current->id)->lockForUpdate()->firstOrFail();

            if ($checkIn->status === 'confirmed') {
                return CheckInResult::Already;
            }

            if ($checkIn->status === 'missed' || $now->gte($checkIn->expires_at)) {
                return CheckInResult::Expired;
            }

            if ($checkIn->attempts >= Setting::int('checkin_max_attempts')) {
                return CheckInResult::Locked;
            }

            if ($now->lt($checkIn->reveal_at)) {
                $checkIn->increment('attempts');

                return CheckInResult::TooEarly;
            }

            if (! hash_equals($checkIn->code, preg_replace('/\D/', '', $input))) {
                $checkIn->increment('attempts');

                return CheckInResult::Wrong;
            }

            $checkIn->update(['status' => 'confirmed', 'confirmed_at' => $now]);

            return CheckInResult::Confirmed;
        });
    }

    /**
     * Mark every unconfirmed check-in past its deadline as missed and eliminate the user from all
     * challenges that were running that day. Safe to run repeatedly.
     *
     * @return int number of check-ins marked as missed
     */
    public function expireDue(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $ids = CheckIn::where('status', 'pending')->where('expires_at', '<=', $now)->pluck('id');
        $missed = 0;

        foreach ($ids as $id) {
            $notifications = [];

            DB::transaction(function () use ($id, &$missed, &$notifications) {
                $checkIn = CheckIn::whereKey($id)->lockForUpdate()->first();

                if ($checkIn?->status !== 'pending') {
                    return;
                }

                $checkIn->update(['status' => 'missed']);
                $missed++;
                $notifications = $this->eliminate($checkIn);
            });

            foreach ($notifications as [$user, $notification]) {
                $user->notify($notification);
            }
        }

        return $missed;
    }

    /**
     * @return array<int, array{0: User, 1: ParticipantEliminated}> notifications to send after commit
     */
    private function eliminate(CheckIn $checkIn): array
    {
        $date = $checkIn->date->toDateString();
        $notifications = [];

        $participants = $checkIn->user->participations()
            ->where('status', 'active')
            ->whereHas('challenge', fn ($q) => $q->where('status', 'active')->where('start_date', '<=', $date)->where('end_date', '>=', $date))
            ->with('challenge.group')
            ->get();

        foreach ($participants as $participant) {
            $participant->update(['status' => 'eliminated', 'eliminated_on' => $date]);
            $notifications[] = [$checkIn->user, new ParticipantEliminated($participant->challenge, $checkIn->date->translatedFormat('j F'))];
        }

        return $notifications;
    }

    private function scheduleFor(User $user, string $date, CarbonImmutable $now): bool
    {
        if (! $this->needsCheckIn($user, $date) || CheckIn::where('user_id', $user->id)->where('date', $date)->exists()) {
            return false;
        }

        $times = $this->prayerTimes->forDate($user->city, $date);
        $fajr = $times->fajr;
        // Everything must finish before sunrise.
        $latestReveal = $times->sunrise->subMinute();

        if ($fajr === null || $fajr->gte($latestReveal)) {
            Log::warning('Check-in skipped: Fajr cannot be determined', ['user_id' => $user->id, 'date' => $date]);

            return false;
        }

        // Only upcoming Fajrs, and only shortly before them.
        if ($fajr->lte($now) || $fajr->gt($now->addHours(Setting::int('checkin_schedule_ahead_hours')))) {
            return false;
        }

        $rangeStart = $fajr->addMinutes(Setting::int('reveal_range_start_min'));
        $revealAt = $rangeStart->addSeconds(random_int(0, Setting::int('reveal_range_length_min') * 60 - 1));
        $revealAt = $revealAt->gt($latestReveal) ? $latestReveal : $revealAt;
        $expiresAt = $revealAt->addMinutes(Setting::int('code_valid_min'));
        $expiresAt = $expiresAt->gt($times->sunrise) ? $times->sunrise : $expiresAt;

        try {
            CheckIn::create([
                'user_id' => $user->id,
                'date' => $date,
                'fajr_at' => $fajr,
                'code' => str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT),
                'reveal_at' => $revealAt,
                'expires_at' => $expiresAt,
                'status' => 'pending',
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /**
     * Whether the user is a live participant of a running challenge that covers this local date.
     */
    private function needsCheckIn(User $user, string $date): bool
    {
        return $user->participations()
            ->where('status', 'active')
            ->whereHas('challenge', fn ($q) => $q->where('status', 'active')->where('start_date', '<=', $date)->where('end_date', '>=', $date))
            ->exists();
    }

    private function rangeStart(CheckIn $checkIn): CarbonImmutable
    {
        return CarbonImmutable::instance($checkIn->fajr_at)->addMinutes(Setting::int('reveal_range_start_min'));
    }
}
