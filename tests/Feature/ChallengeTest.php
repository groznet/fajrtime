<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Group;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ChallengeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function member(Group $group, int $balance = 1000_00): User
    {
        $user = User::factory()->create(['balance_available' => $balance]);
        $group->members()->attach($user->id);

        return $user;
    }

    private function recruiting(): Challenge
    {
        return Challenge::factory()->create();
    }

    public function test_creator_opens_a_challenge_with_frozen_rules(): void
    {
        CarbonImmutable::setTestNow('2026-11-01 10:00:00');
        $group = Group::factory()->create(['stake_amount' => 300_00, 'cycle_days' => 10]);
        $start = '2026-11-05';

        $this->actingAs($group->creator)->post(route('challenges.store', $group), ['start_date' => $start])->assertRedirect();

        $challenge = $group->challenges()->firstOrFail();
        $this->assertSame('recruiting', $challenge->status);
        $this->assertSame(300_00, $challenge->stake_amount);
        $this->assertSame('2026-11-05', $challenge->start_date->toDateString());
        $this->assertSame('2026-11-14', $challenge->end_date->toDateString());

        $group->update(['stake_amount' => 900_00]);
        $this->assertSame(300_00, $challenge->fresh()->stake_amount);
    }

    public function test_only_the_creator_opens_challenges_and_only_one_at_a_time(): void
    {
        CarbonImmutable::setTestNow('2026-11-01 10:00:00');
        $group = Group::factory()->create();
        $other = $this->member($group);

        $this->actingAs($other)->post(route('challenges.store', $group), ['start_date' => '2026-11-05'])->assertForbidden();

        $this->actingAs($group->creator)->post(route('challenges.store', $group), ['start_date' => '2026-11-05']);
        $this->post(route('challenges.store', $group), ['start_date' => '2026-11-10'])->assertSessionHasErrors('start_date');
        $this->assertSame(1, $group->challenges()->count());
    }

    public function test_start_date_must_be_in_the_future(): void
    {
        CarbonImmutable::setTestNow('2026-11-01 10:00:00');
        $group = Group::factory()->create();

        $this->actingAs($group->creator)->post(route('challenges.store', $group), ['start_date' => '2026-11-01'])
            ->assertSessionHasErrors('start_date');
        $this->post(route('challenges.store', $group), ['start_date' => 'tomorrow'])->assertSessionHasErrors('start_date');
        $this->assertSame(0, $group->challenges()->count());
    }

    public function test_joining_locks_the_stake_in_the_ledger(): void
    {
        $challenge = $this->recruiting();
        $user = $this->member($challenge->group, 800_00);

        $this->actingAs($user)->post(route('challenges.join', $challenge))->assertRedirect();

        $user->refresh();
        $this->assertSame(300_00, $user->balance_available);
        $this->assertSame(500_00, $user->balance_locked);
        $this->assertSame(1, $challenge->participants()->where('status', 'active')->count());
        $this->assertSame(
            [-500_00, 500_00],
            Transaction::where('type', 'stake_lock')->where('challenge_id', $challenge->id)->orderBy('id')->pluck('amount')->all(),
        );
    }

    public function test_cannot_join_without_enough_balance(): void
    {
        $challenge = $this->recruiting();
        $user = $this->member($challenge->group, 499_99);

        $this->actingAs($user)->post(route('challenges.join', $challenge))->assertSessionHasErrors('balance');

        $this->assertSame(499_99, $user->fresh()->balance_available);
        $this->assertSame(0, $challenge->participants()->count());
        $this->assertSame(0, Transaction::count());
    }

    public function test_cannot_join_twice_or_without_group_membership(): void
    {
        $challenge = $this->recruiting();
        $member = $this->member($challenge->group);
        $outsider = User::factory()->create(['balance_available' => 1000_00]);

        $this->actingAs($member)->post(route('challenges.join', $challenge));
        $this->post(route('challenges.join', $challenge))->assertSessionHasErrors('challenge');
        $this->assertSame(500_00, $member->fresh()->balance_locked);

        $this->actingAs($outsider)->post(route('challenges.join', $challenge))->assertSessionHasErrors('challenge');
        $this->assertSame(0, $outsider->fresh()->balance_locked);
    }

    public function test_cannot_join_when_the_challenge_is_full_or_not_recruiting(): void
    {
        config(['fajr.defaults.max_participants' => 1]);
        $challenge = $this->recruiting();
        $first = $this->member($challenge->group);
        $second = $this->member($challenge->group);

        $this->actingAs($first)->post(route('challenges.join', $challenge));
        $this->actingAs($second)->post(route('challenges.join', $challenge))->assertSessionHasErrors('challenge');

        $challenge->update(['status' => 'active']);
        config(['fajr.defaults.max_participants' => 100]);
        $this->actingAs($second)->post(route('challenges.join', $challenge))->assertSessionHasErrors('challenge');
        $this->assertSame(0, $second->fresh()->balance_locked);
    }

    public function test_leaving_while_recruiting_returns_the_stake_and_allows_rejoining(): void
    {
        $challenge = $this->recruiting();
        $user = $this->member($challenge->group, 500_00);

        $this->actingAs($user)->post(route('challenges.join', $challenge));
        $this->post(route('challenges.leave', $challenge))->assertRedirect();

        $user->refresh();
        $this->assertSame(500_00, $user->balance_available);
        $this->assertSame(0, $user->balance_locked);
        $this->assertSame('left', $challenge->participants()->first()->status);

        $this->post(route('challenges.join', $challenge))->assertRedirect();
        $this->assertSame(500_00, $user->fresh()->balance_locked);
        $this->assertSame(1, $challenge->participants()->count());
    }

    public function test_cannot_leave_an_active_challenge(): void
    {
        $challenge = $this->recruiting();
        $user = $this->member($challenge->group);
        $this->actingAs($user)->post(route('challenges.join', $challenge));
        $challenge->update(['status' => 'active']);

        $this->post(route('challenges.leave', $challenge))->assertSessionHasErrors('challenge');
        $this->assertSame(500_00, $user->fresh()->balance_locked);
    }

    public function test_due_challenge_with_enough_participants_becomes_active(): void
    {
        CarbonImmutable::setTestNow('2026-11-05 00:30:00', config('fajr.challenge_timezone'));
        $challenge = Challenge::factory()->create(['start_date' => '2026-11-05', 'end_date' => '2026-11-11']);

        foreach (range(1, 3) as $_) {
            $challenge->participants()->create(['user_id' => $this->member($challenge->group)->id]);
        }

        $this->assertSame(1, app(ChallengeService::class)->startDue());
        $this->assertSame('active', $challenge->fresh()->status);
        $this->assertSame(0, app(ChallengeService::class)->startDue());
    }

    public function test_due_challenge_without_enough_participants_is_cancelled_and_stakes_returned(): void
    {
        $challenge = Challenge::factory()->create(['start_date' => now()->addDays(2), 'end_date' => now()->addDays(8)]);
        $a = $this->member($challenge->group, 500_00);
        $b = $this->member($challenge->group, 700_00);
        $this->actingAs($a)->post(route('challenges.join', $challenge));
        $this->actingAs($b)->post(route('challenges.join', $challenge));

        CarbonImmutable::setTestNow(now()->addDays(2)->setTimezone(config('fajr.challenge_timezone'))->setTime(0, 5));
        app(ChallengeService::class)->startDue();

        $this->assertSame('cancelled', $challenge->fresh()->status);
        $this->assertSame([500_00, 0], [$a->fresh()->balance_available, $a->fresh()->balance_locked]);
        $this->assertSame([700_00, 0], [$b->fresh()->balance_available, $b->fresh()->balance_locked]);
        $this->assertSame(0, $challenge->participants()->where('status', 'active')->count());

        // Running it again must not refund twice.
        app(ChallengeService::class)->startDue();
        $this->assertSame(500_00, $a->fresh()->balance_available);
    }

    public function test_future_challenge_is_not_started_early(): void
    {
        $challenge = Challenge::factory()->create();

        $this->assertSame(0, app(ChallengeService::class)->startDue());
        $this->assertSame('recruiting', $challenge->fresh()->status);
    }

    public function test_recruiting_closes_when_the_start_date_arrives(): void
    {
        $challenge = $this->recruiting();
        $user = $this->member($challenge->group);

        CarbonImmutable::setTestNow($challenge->start_date->setTimezone(config('fajr.challenge_timezone'))->shiftTimezone(config('fajr.challenge_timezone'))->setTime(0, 1));
        $this->actingAs($user)->post(route('challenges.join', $challenge))->assertSessionHasErrors('challenge');
        $this->assertSame(0, $user->fresh()->balance_locked);
    }

    public function test_withdrawal_and_city_change_are_blocked_during_a_challenge(): void
    {
        $challenge = $this->recruiting();
        $user = $this->member($challenge->group, 1000_00);
        $otherCity = \App\Models\City::factory()->create();
        $this->actingAs($user)->post(route('challenges.join', $challenge));

        $this->patch('/settings', ['name' => $user->name, 'email' => $user->email, 'city_id' => $otherCity->id])
            ->assertSessionHasErrors('city_id');
        $this->assertNotSame($otherCity->id, $user->fresh()->city_id);

        $this->post('/wallet/withdrawals', ['amount' => 200, 'phone' => '+79990000000', 'bank' => 'Сбербанк'])
            ->assertSessionHasErrorsIn('withdrawal', 'amount');
        $this->assertSame(500_00, $user->fresh()->balance_available);

        $this->post(route('challenges.leave', $challenge));
        $this->patch('/settings', ['name' => $user->name, 'email' => $user->email, 'city_id' => $otherCity->id])->assertSessionHasNoErrors();
        $this->assertSame($otherCity->id, $user->fresh()->city_id);
    }

    public function test_challenge_page_respects_group_privacy(): void
    {
        $challenge = Challenge::factory()->create(['group_id' => Group::factory()->private()]);
        $outsider = User::factory()->create();
        $member = $this->member($challenge->group);

        $this->actingAs($outsider)->get(route('challenges.show', $challenge))->assertNotFound();
        $this->actingAs($member)->get(route('challenges.show', $challenge))->assertOk();
        $this->get(route('challenges.show', $challenge))->assertOk();
    }

    public function test_group_page_shows_the_right_actions(): void
    {
        $challenge = $this->recruiting();
        $group = $challenge->group;
        $group->members()->attach($group->creator_id);
        $poor = $this->member($group, 200_00);
        $rich = $this->member($group, 1000_00);

        $this->actingAs($rich)->get(route('groups.show', $group))->assertOk()->assertSee('Участвовать за');
        // 300 ₽ short -> must top up 300 / 0.95 = 315,79 ₽ gross.
        $this->actingAs($poor)->get(route('groups.show', $group))->assertOk()->assertSee('315,79')->assertDontSee('Участвовать за');
        $this->actingAs(User::factory()->create())->get(route('groups.show', $group))->assertOk()->assertSee('Вступить в группу');
    }

    public function test_guest_sees_a_registration_prompt_on_a_group_page(): void
    {
        $this->get(route('groups.show', Group::factory()->create()))->assertOk()->assertSee('Зарегистрироваться и вступить');
    }

    public function test_creator_sees_the_start_form_when_there_is_no_challenge(): void
    {
        $group = Group::factory()->create();

        $this->actingAs($group->creator)->get(route('groups.show', $group))->assertOk()->assertSee('Запустить набор');
    }

    public function test_dashboard_and_my_groups_list_the_users_challenge(): void
    {
        $challenge = $this->recruiting();
        $user = $this->member($challenge->group);
        $this->actingAs($user)->post(route('challenges.join', $challenge));

        $this->get('/dashboard')->assertOk()->assertSee($challenge->group->name)->assertSee('Набор участников');
        $this->get('/my/groups')->assertOk()->assertSee($challenge->group->name);
        $this->get(route('challenges.show', $challenge))->assertOk()->assertSee($user->name)->assertSee('Выйти из челленджа');
    }
}
