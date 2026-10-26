<?php

namespace Tests\Feature;

use App\Enums\CheckInResult;
use App\Models\Challenge;
use App\Models\CheckIn;
use App\Models\City;
use App\Models\User;
use App\Notifications\ParticipantEliminated;
use App\Services\CheckInService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    // Grozny, 2026-03-20: Fajr 04:37 Moscow time = 01:37 UTC.
    private const NOW = '2026-03-20 00:00:00';

    private function at(string $time): CarbonImmutable
    {
        $now = CarbonImmutable::parse($time, 'UTC');
        $this->travelTo($now);

        return $now;
    }

    private function service(): CheckInService
    {
        return app(CheckInService::class);
    }

    /**
     * A user who is an active participant of a running challenge covering 2026-03-20.
     */
    private function racer(?Challenge $challenge = null, array $userState = []): User
    {
        $challenge ??= Challenge::factory()->create(['status' => 'active', 'start_date' => '2026-03-18', 'end_date' => '2026-03-26']);
        $user = User::factory()->create($userState);
        $challenge->group->members()->attach($user->id);
        $challenge->participants()->create(['user_id' => $user->id]);

        return $user;
    }

    private function pending(User $user, string $reveal = '2026-03-20 01:40:00', string $expires = '2026-03-20 01:45:00', array $extra = []): CheckIn
    {
        return CheckIn::factory()->create(array_merge([
            'user_id' => $user->id,
            'date' => '2026-03-20',
            'fajr_at' => '2026-03-20 01:37:00',
            'code' => '482913',
            'reveal_at' => $reveal,
            'expires_at' => $expires,
        ], $extra));
    }

    // --- scheduling -------------------------------------------------------------------------

    public function test_schedule_creates_a_row_with_a_random_reveal_inside_the_range(): void
    {
        $now = $this->at(self::NOW);
        $user = $this->racer();

        $this->assertSame(1, $this->service()->scheduleDue($now));

        $checkIn = CheckIn::firstWhere('user_id', $user->id);
        $fajr = CarbonImmutable::parse('2026-03-20 01:37:00', 'UTC');
        $this->assertSame('2026-03-20', $checkIn->date->toDateString());
        $this->assertSame('pending', $checkIn->status);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $checkIn->code);
        $this->assertEqualsWithDelta($fajr->timestamp, $checkIn->fajr_at->timestamp, 90);
        $this->assertTrue($checkIn->reveal_at->gte($checkIn->fajr_at));
        $this->assertTrue($checkIn->reveal_at->lt($checkIn->fajr_at->addMinutes(10)));
        $this->assertEquals(300, $checkIn->reveal_at->diffInSeconds($checkIn->expires_at));
    }

    public function test_schedule_is_idempotent(): void
    {
        $now = $this->at(self::NOW);
        $this->racer();

        $this->service()->scheduleDue($now);
        $this->assertSame(0, $this->service()->scheduleDue($now));
        $this->assertSame(1, CheckIn::count());
    }

    public function test_expiry_is_capped_at_sunrise(): void
    {
        $now = $this->at(self::NOW);
        $this->racer();
        config(['fajr.defaults.reveal_range_start_min' => 90, 'fajr.defaults.reveal_range_length_min' => 10]);

        $this->service()->scheduleDue($now);

        $checkIn = CheckIn::firstOrFail();
        $sunrise = app(\App\Services\PrayerTimeService::class)->forDate(City::first(), '2026-03-20')->sunrise;
        $this->assertTrue($checkIn->reveal_at->lt($sunrise));
        $this->assertTrue($checkIn->expires_at->lte($sunrise));
    }

    public function test_schedule_skips_users_outside_a_running_challenge(): void
    {
        $now = $this->at(self::NOW);
        $recruiting = Challenge::factory()->create(['status' => 'recruiting', 'start_date' => '2026-03-20', 'end_date' => '2026-03-26']);
        $this->racer($recruiting);
        $over = Challenge::factory()->create(['status' => 'active', 'start_date' => '2026-03-10', 'end_date' => '2026-03-19']);
        $this->racer($over);
        $eliminated = $this->racer();
        $eliminated->participations()->update(['status' => 'eliminated']);

        $this->assertSame(0, $this->service()->scheduleDue($now));
    }

    public function test_schedule_only_creates_rows_shortly_before_fajr(): void
    {
        $this->racer();

        // 16 hours before Fajr: too early.
        $this->assertSame(0, $this->service()->scheduleDue($this->at('2026-03-19 09:30:00')));
        // After Fajr has passed: too late.
        $this->assertSame(0, $this->service()->scheduleDue($this->at('2026-03-20 02:00:00')));
        // Evening before: tomorrow's Fajr is within 12 hours.
        $this->assertSame(1, $this->service()->scheduleDue($this->at('2026-03-19 17:00:00')));
        $this->assertSame('2026-03-20', CheckIn::first()->date->toDateString());
    }

    public function test_schedule_skips_days_when_fajr_cannot_be_calculated(): void
    {
        $now = $this->at('2026-06-20 00:00:00');
        $challenge = Challenge::factory()->create(['status' => 'active', 'start_date' => '2026-06-18', 'end_date' => '2026-06-26']);
        $north = City::factory()->create(['latitude' => 59.93, 'longitude' => 30.33]);
        $this->racer($challenge, ['city_id' => $north->id]);

        $this->assertSame(0, $this->service()->scheduleDue($now));
    }

    // --- what the browser may see -------------------------------------------------------------

    public function test_the_code_is_not_revealed_before_reveal_time(): void
    {
        $user = $this->racer();
        $this->pending($user);

        $state = $this->service()->state($user, $this->at('2026-03-20 01:39:59'));

        $this->assertSame('waiting', $state['status']);
        $this->assertStringNotContainsString('482913', json_encode($state));
        $this->assertArrayNotHasKey('code', $state);
        $this->assertArrayNotHasKey('expires_at', $state);
        $this->assertSame('04:37', $state['range_from']);
        $this->assertSame('04:47', $state['range_to']);
    }

    public function test_http_state_and_page_never_leak_the_code_before_reveal(): void
    {
        $user = $this->racer();
        $this->pending($user);
        $this->at('2026-03-20 01:38:00');

        $this->actingAs($user)->getJson('/check-in/state')->assertOk()->assertJsonPath('status', 'waiting')
            ->assertJsonMissingPath('code');
        $this->get('/check-in')->assertOk()->assertDontSee('482913');
        $this->get('/dashboard')->assertOk()->assertDontSee('482913');
    }

    public function test_the_code_is_revealed_after_reveal_time_until_it_expires(): void
    {
        $user = $this->racer();
        $this->pending($user);

        $revealed = $this->service()->state($user, $this->at('2026-03-20 01:40:00'));
        $this->assertSame('revealed', $revealed['status']);
        $this->assertSame('482913', $revealed['code']);

        $this->assertSame('missed', $this->service()->state($user, $this->at('2026-03-20 01:45:00'))['status']);
    }

    // --- submitting a code --------------------------------------------------------------------

    public function test_correct_code_confirms_the_day(): void
    {
        $user = $this->racer();
        $checkIn = $this->pending($user);

        $this->assertSame(CheckInResult::Confirmed, $this->service()->submit($user, '482913', $this->at('2026-03-20 01:41:00')));

        $checkIn->refresh();
        $this->assertSame('confirmed', $checkIn->status);
        $this->assertNotNull($checkIn->confirmed_at);
        $this->assertSame(CheckInResult::Already, $this->service()->submit($user, '482913'));
        $this->assertSame('confirmed', $this->service()->state($user)['status']);
    }

    public function test_input_is_normalised_to_digits(): void
    {
        $user = $this->racer();
        $this->pending($user);

        $this->assertSame(CheckInResult::Confirmed, $this->service()->submit($user, ' 482 913 ', $this->at('2026-03-20 01:41:00')));
    }

    public function test_wrong_codes_are_counted_and_lock_the_day_after_the_limit(): void
    {
        $user = $this->racer();
        $checkIn = $this->pending($user);
        $now = $this->at('2026-03-20 01:41:00');

        foreach (range(1, 5) as $_) {
            $this->assertSame(CheckInResult::Wrong, $this->service()->submit($user, '000000', $now));
        }

        $this->assertSame(5, $checkIn->fresh()->attempts);
        // Even the right code is refused now.
        $this->assertSame(CheckInResult::Locked, $this->service()->submit($user, '482913', $now));
        $this->assertSame('pending', $checkIn->fresh()->status);
        $this->assertSame('locked', $this->service()->state($user, $now)['status']);
    }

    public function test_entering_the_code_before_reveal_is_refused_and_counts_as_an_attempt(): void
    {
        $user = $this->racer();
        $checkIn = $this->pending($user);

        // Even a lucky guess of the right code before it appears does not confirm the day.
        $this->assertSame(CheckInResult::TooEarly, $this->service()->submit($user, '482913', $this->at('2026-03-20 01:38:00')));
        $this->assertSame('pending', $checkIn->fresh()->status);
        $this->assertSame(1, $checkIn->fresh()->attempts);
    }

    public function test_code_cannot_be_entered_after_it_expires(): void
    {
        $user = $this->racer();
        $checkIn = $this->pending($user);

        $this->assertSame(CheckInResult::Expired, $this->service()->submit($user, '482913', $this->at('2026-03-20 01:45:01')));
        $this->assertSame('pending', $checkIn->fresh()->status);
    }

    public function test_submit_without_a_check_in_does_nothing(): void
    {
        $this->assertSame(CheckInResult::None, $this->service()->submit(User::factory()->create(), '123456'));
    }

    public function test_codes_are_per_user(): void
    {
        $alice = $this->racer();
        $bob = $this->racer();
        $this->pending($alice);
        $this->pending($bob, extra: ['code' => '111111']);

        $this->assertSame(CheckInResult::Wrong, $this->service()->submit($bob, '482913', $this->at('2026-03-20 01:41:00')));
        $this->assertSame(CheckInResult::Confirmed, $this->service()->submit($alice, '482913'));
    }

    public function test_http_submit_returns_json_results(): void
    {
        $user = $this->racer();
        $this->pending($user);
        $this->at('2026-03-20 01:41:00');

        $this->actingAs($user)->postJson('/check-in', ['code' => '000000'])
            ->assertStatus(422)->assertJsonPath('result', 'wrong');
        $this->postJson('/check-in', ['code' => '482913'])
            ->assertOk()->assertJsonPath('result', 'confirmed')->assertJsonPath('state.status', 'confirmed');
        $this->postJson('/check-in', [])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_check_in_endpoints_require_login(): void
    {
        $this->get('/check-in')->assertRedirect('/login');
        $this->getJson('/check-in/state')->assertUnauthorized();
        $this->postJson('/check-in', ['code' => '123456'])->assertUnauthorized();
    }

    // --- expiry and elimination ---------------------------------------------------------------

    public function test_missed_check_in_eliminates_the_user_from_all_running_challenges_of_that_day(): void
    {
        Notification::fake();
        $a = Challenge::factory()->create(['status' => 'active', 'start_date' => '2026-03-18', 'end_date' => '2026-03-26']);
        $b = Challenge::factory()->create(['status' => 'active', 'start_date' => '2026-03-20', 'end_date' => '2026-03-26']);
        $notYetStarted = Challenge::factory()->create(['status' => 'active', 'start_date' => '2026-03-21', 'end_date' => '2026-03-27']);
        $user = $this->racer($a);
        foreach ([$b, $notYetStarted] as $other) {
            $other->group->members()->attach($user->id);
            $other->participants()->create(['user_id' => $user->id]);
        }
        $checkIn = $this->pending($user);

        $this->assertSame(1, $this->service()->expireDue($this->at('2026-03-20 01:45:00')));

        $this->assertSame('missed', $checkIn->fresh()->status);
        $statuses = $user->participations()->pluck('status', 'challenge_id');
        $this->assertSame('eliminated', $statuses[$a->id]);
        $this->assertSame('eliminated', $statuses[$b->id]);
        $this->assertSame('active', $statuses[$notYetStarted->id]);
        $this->assertSame('2026-03-20', $user->participations()->where('challenge_id', $a->id)->first()->eliminated_on->toDateString());
        Notification::assertSentToTimes($user, ParticipantEliminated::class, 2);
    }

    public function test_expiry_is_idempotent_and_leaves_confirmed_and_open_check_ins_alone(): void
    {
        Notification::fake();
        $missed = $this->racer();
        $confirmed = $this->racer();
        $open = $this->racer();
        $this->pending($missed);
        $this->pending($confirmed, extra: ['status' => 'confirmed', 'confirmed_at' => '2026-03-20 01:41:00']);
        $this->pending($open, '2026-03-20 01:42:00', '2026-03-20 01:50:00');
        $now = $this->at('2026-03-20 01:46:00');

        $this->assertSame(1, $this->service()->expireDue($now));
        $this->assertSame(0, $this->service()->expireDue($now));

        $this->assertSame('eliminated', $missed->participations()->first()->status);
        $this->assertSame('active', $confirmed->participations()->first()->status);
        $this->assertSame('active', $open->participations()->first()->status);
        Notification::assertSentToTimes($missed, ParticipantEliminated::class, 1);
    }

    public function test_elimination_creates_a_database_notification_shown_on_the_notifications_page(): void
    {
        $user = $this->racer();
        $this->pending($user);

        $this->service()->expireDue($this->at('2026-03-20 01:46:00'));

        $this->assertSame(1, $user->unreadNotifications()->count());
        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Вы выбыли из челленджа');
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_commands_run(): void
    {
        $this->artisan('checkins:schedule')->assertSuccessful();
        $this->artisan('checkins:expire')->assertSuccessful();
    }

    // --- pages --------------------------------------------------------------------------------

    public function test_dashboard_shows_the_check_in_widget_only_during_a_running_challenge(): void
    {
        $this->at(self::NOW);
        $idle = User::factory()->create();
        $this->actingAs($idle)->get('/dashboard')->assertDontSee('Подтверждение подъёма');

        $racer = $this->racer();
        $this->actingAs($racer)->get('/dashboard')->assertOk()->assertSee('Подтверждение подъёма');
    }
}
