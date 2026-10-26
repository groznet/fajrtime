<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\CheckIn;
use App\Models\Group;
use App\Models\User;
use App\Services\ChallengeService;
use App\Services\CheckInService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The whole core loop through the real services and scheduled commands, with the clock moved by hand.
 */
class ChallengeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $utc): void
    {
        $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
    }

    /** Opens the check-in of $user on $date at its reveal time and returns the code the browser would see. */
    private function revealedCode(User $user): string
    {
        $checkIn = CheckIn::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->travelTo($checkIn->reveal_at->addSeconds(30));
        $state = app(CheckInService::class)->state($user);
        $this->assertSame('revealed', $state['status']);

        return $state['code'];
    }

    public function test_group_challenge_from_join_to_payout(): void
    {
        Notification::fake();

        // Four friends, stake 500 ₽, a two-day challenge on 20-21 March.
        $this->at('2026-03-18 09:00:00');
        $group = Group::factory()->create(['stake_amount' => 500_00, 'cycle_days' => 2]);
        $challenge = app(ChallengeService::class)->create($group, CarbonImmutable::parse('2026-03-20'));

        [$alice, $bob, $carol, $dan] = collect(range(1, 4))->map(function () use ($group, $challenge) {
            $user = User::factory()->create(['balance_available' => 500_00]);
            $group->members()->attach($user->id);
            app(ChallengeService::class)->join($user, $challenge);

            return $user;
        })->all();
        $this->assertSame(0, $alice->fresh()->balance_available);
        $this->assertSame(500_00, $alice->fresh()->balance_locked);

        // Recruiting closes at midnight Moscow on the 20th: the challenge starts.
        $this->at('2026-03-19 21:05:00');
        $this->artisan('challenges:start-due')->assertSuccessful();
        $this->assertSame('active', $challenge->fresh()->status);

        // Day 1: everyone but Carol confirms.
        $this->artisan('checkins:schedule')->assertSuccessful();
        $this->assertSame(4, CheckIn::count());
        foreach ([$alice, $bob, $dan] as $user) {
            $code = $this->revealedCode($user);
            $this->actingAs($user)->postJson('/check-in', ['code' => $code])->assertOk();
        }
        $this->at('2026-03-20 02:30:00');
        $this->artisan('checkins:expire')->assertSuccessful();
        $this->assertSame('eliminated', $challenge->participants()->where('user_id', $carol->id)->first()->status);
        $this->assertSame(3, $challenge->participants()->where('status', 'active')->count());

        // Day 2: Dan sleeps in.
        $this->at('2026-03-20 21:00:00');
        $this->artisan('checkins:schedule')->assertSuccessful();
        $this->assertSame(3, CheckIn::where('date', '2026-03-21')->count(), 'the eliminated user gets no check-in');
        foreach ([$alice, $bob] as $user) {
            $code = $this->revealedCode($user);
            $this->actingAs($user)->postJson('/check-in', ['code' => $code])->assertOk();
        }
        $this->at('2026-03-21 02:30:00');
        $this->artisan('checkins:expire')->assertSuccessful();
        $this->assertSame('eliminated', $challenge->participants()->where('user_id', $dan->id)->first()->status);

        // The last day is not over yet in Moscow: nothing is paid out.
        $this->at('2026-03-21 20:00:00');
        $this->artisan('challenges:settle')->assertSuccessful();
        $this->assertSame('active', $challenge->fresh()->status);

        // After Moscow midnight the challenge is settled: pool 1 000 ₽ between two survivors.
        $this->at('2026-03-21 21:30:00');
        $this->artisan('challenges:settle')->assertSuccessful();
        $challenge->refresh();
        $this->assertSame('completed', $challenge->status);
        $this->assertSame(1000_00, $challenge->forfeit_pool);
        $this->assertSame(500_00, $challenge->survivor_share);

        foreach ([$alice, $bob] as $winner) {
            $winner->refresh();
            $this->assertSame([1000_00, 0], [$winner->balance_available, $winner->balance_locked]);
        }
        foreach ([$carol, $dan] as $loser) {
            $loser->refresh();
            $this->assertSame([0, 0], [$loser->balance_available, $loser->balance_locked]);
        }

        // Running everything again changes nothing.
        $this->artisan('challenges:settle');
        $this->assertSame(1000_00, $alice->fresh()->balance_available);
    }
}
