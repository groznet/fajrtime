<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\CheckIn;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SettlementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The challenge below ended on 2026-03-10; it is 2026-03-11 in Moscow.
        $this->travelTo(CarbonImmutable::parse('2026-03-11 07:00:00', 'UTC'));
    }

    /**
     * An active, finished challenge where everyone's stake is locked.
     *
     * @return array{0: Challenge, 1: \Illuminate\Support\Collection<int, User>}
     */
    private function finishedChallenge(int $survivors, int $eliminated, int $stake = 475_00, int $left = 0): array
    {
        $challenge = Challenge::factory()->create([
            'status' => 'active',
            'stake_amount' => $stake,
            'start_date' => '2026-03-04',
            'end_date' => '2026-03-10',
        ]);

        $users = collect();
        foreach (['active' => $survivors, 'eliminated' => $eliminated] as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $users->push($this->participant($challenge, $status, $stake));
            }
        }

        for ($i = 0; $i < $left; $i++) {
            $challenge->participants()->create(['user_id' => User::factory()->create()->id, 'status' => 'left']);
        }

        return [$challenge, $users];
    }

    private function participant(Challenge $challenge, string $status, int $locked): User
    {
        $user = User::factory()->create(['balance_available' => 0, 'balance_locked' => $locked]);
        $challenge->participants()->create([
            'user_id' => $user->id,
            'status' => $status,
            'eliminated_on' => $status === 'eliminated' ? '2026-03-06' : null,
        ]);

        return $user;
    }

    private function settle(Challenge $challenge): bool
    {
        return app(SettlementService::class)->settle($challenge->id);
    }

    public function test_worked_example_from_the_spec(): void
    {
        // 30 participants, stake 475 ₽, 10 eliminated -> pool 4 750 ₽, 20 survivors -> 237,50 ₽ each.
        [$challenge, $users] = $this->finishedChallenge(survivors: 20, eliminated: 10);

        $this->assertTrue($this->settle($challenge));

        $challenge->refresh();
        $this->assertSame('completed', $challenge->status);
        $this->assertSame(4_750_00, $challenge->forfeit_pool);
        $this->assertSame(237_50, $challenge->survivor_share);

        $survivors = $challenge->participants()->where('status', 'survived')->get();
        $this->assertCount(20, $survivors);
        foreach ($survivors as $participant) {
            $this->assertSame(712_50, $participant->payout_amount);
            $user = $participant->user->fresh();
            $this->assertSame(712_50, $user->balance_available);
            $this->assertSame(0, $user->balance_locked);
        }

        foreach ($challenge->participants()->where('status', 'eliminated')->get() as $participant) {
            $user = $participant->user->fresh();
            $this->assertSame([0, 0], [$user->balance_available, $user->balance_locked]);
        }
    }

    public function test_everyone_gets_the_stake_back_when_nobody_misses(): void
    {
        [$challenge] = $this->finishedChallenge(survivors: 4, eliminated: 0, stake: 300_00);

        $this->settle($challenge);

        $challenge->refresh();
        $this->assertSame(0, $challenge->forfeit_pool);
        $this->assertSame(0, $challenge->survivor_share);
        foreach ($challenge->participants as $participant) {
            $this->assertSame('survived', $participant->status);
            $this->assertSame(300_00, $participant->payout_amount);
            $this->assertSame(300_00, $participant->user->fresh()->balance_available);
        }
    }

    public function test_leftover_kopecks_stay_with_the_platform(): void
    {
        // Pool 2 x 100,00 = 200,00 ₽ split between 3 survivors -> 66,66 each, 0,02 left over.
        [$challenge, $users] = $this->finishedChallenge(survivors: 3, eliminated: 2, stake: 100_00);
        $before = $users->sum(fn (User $u) => $u->balance_available + $u->balance_locked);

        $this->settle($challenge);

        $this->assertSame(66_66, $challenge->fresh()->survivor_share);
        $after = $users->sum(fn (User $u) => $u->fresh()->balance_available + $u->fresh()->balance_locked);
        $this->assertSame(2, $before - $after);
    }

    public function test_money_is_conserved_apart_from_the_remainder(): void
    {
        [$challenge, $users] = $this->finishedChallenge(survivors: 7, eliminated: 5, stake: 150_00);
        $before = $users->sum(fn (User $u) => $u->balance_available + $u->balance_locked);
        $remainder = (5 * 150_00) % 7;

        $this->settle($challenge);

        $after = $users->sum(fn (User $u) => $u->fresh()->balance_available + $u->fresh()->balance_locked);
        $this->assertSame($remainder, $before - $after);
    }

    public function test_ledger_rows_back_every_balance_change(): void
    {
        [$challenge, $users] = $this->finishedChallenge(survivors: 2, eliminated: 1, stake: 200_00);

        $this->settle($challenge);

        $survivor = $challenge->participants()->where('status', 'survived')->first()->user;
        $this->assertSame(
            [['stake_unlock', 'locked', -200_00], ['payout', 'available', 300_00]],
            Transaction::where('user_id', $survivor->id)->orderBy('id')->get()->map(fn ($t) => [$t->type, $t->affects, $t->amount])->all(),
        );

        $loser = $challenge->participants()->where('status', 'eliminated')->first()->user;
        $this->assertSame(
            [['forfeit', 'locked', -200_00]],
            Transaction::where('user_id', $loser->id)->get()->map(fn ($t) => [$t->type, $t->affects, $t->amount])->all(),
        );
    }

    public function test_settling_twice_pays_only_once(): void
    {
        [$challenge] = $this->finishedChallenge(survivors: 2, eliminated: 2);

        $this->assertTrue($this->settle($challenge));
        $ledger = Transaction::count();
        $balances = User::orderBy('id')->pluck('balance_available')->all();

        $this->assertFalse($this->settle($challenge));
        $this->assertSame($ledger, Transaction::count());
        $this->assertSame($balances, User::orderBy('id')->pluck('balance_available')->all());
    }

    public function test_participants_who_left_are_untouched(): void
    {
        [$challenge] = $this->finishedChallenge(survivors: 2, eliminated: 1, left: 2);

        $this->settle($challenge);

        $this->assertSame(2, $challenge->participants()->where('status', 'left')->count());
        $this->assertSame(2, Transaction::where('type', 'payout')->count());
    }

    public function test_when_everyone_is_eliminated_the_pool_is_not_paid_out(): void
    {
        [$challenge, $users] = $this->finishedChallenge(survivors: 0, eliminated: 3, stake: 100_00);

        $this->assertTrue($this->settle($challenge));

        $challenge->refresh();
        $this->assertSame('completed', $challenge->status);
        $this->assertSame(300_00, $challenge->forfeit_pool);
        $this->assertSame(0, $challenge->survivor_share);
        $this->assertSame(0, Transaction::where('type', 'payout')->count());
        $this->assertSame(0, $users->sum(fn (User $u) => $u->fresh()->balance_locked));
    }

    public function test_challenge_is_not_settled_before_its_last_day_is_over(): void
    {
        [$challenge] = $this->finishedChallenge(survivors: 2, eliminated: 1);
        // Still the last day in Moscow.
        $this->travelTo(CarbonImmutable::parse('2026-03-10 20:59:00', 'UTC'));

        $this->assertFalse($this->settle($challenge));
        $this->assertSame('active', $challenge->fresh()->status);
        $this->assertSame(0, Transaction::count());
    }

    public function test_challenge_waits_while_a_check_in_is_still_unresolved(): void
    {
        [$challenge, $users] = $this->finishedChallenge(survivors: 2, eliminated: 0);
        CheckIn::factory()->create([
            'user_id' => $users->first()->id,
            'date' => '2026-03-10',
            'fajr_at' => '2026-03-10 01:00:00',
            'reveal_at' => '2026-03-10 01:05:00',
            'expires_at' => '2026-03-10 01:10:00',
            'status' => 'pending',
        ]);

        $this->assertFalse($this->settle($challenge));
        $this->assertSame('active', $challenge->fresh()->status);

        // Once the check-in is resolved (and the user eliminated), settlement goes ahead.
        CheckIn::query()->update(['status' => 'confirmed']);
        $this->assertTrue($this->settle($challenge));
    }

    public function test_only_active_challenges_are_settled(): void
    {
        [$recruiting] = $this->finishedChallenge(survivors: 1, eliminated: 0);
        $recruiting->update(['status' => 'recruiting']);
        [$completed] = $this->finishedChallenge(survivors: 1, eliminated: 0);
        $completed->update(['status' => 'completed']);

        $this->assertFalse($this->settle($recruiting));
        $this->assertFalse($this->settle($completed));
        $this->assertSame(0, Transaction::count());
    }

    public function test_settle_due_and_the_command_process_every_finished_challenge(): void
    {
        $this->finishedChallenge(survivors: 2, eliminated: 1);
        $this->finishedChallenge(survivors: 3, eliminated: 0);
        Challenge::factory()->create(['status' => 'active', 'start_date' => '2026-03-10', 'end_date' => '2026-03-16']);

        $this->assertSame(2, app(SettlementService::class)->settleDue());
        $this->assertSame(0, app(SettlementService::class)->settleDue());
        $this->artisan('challenges:settle')->assertSuccessful();
    }

    public function test_payouts_free_the_balance_for_withdrawals_and_city_changes(): void
    {
        [$challenge] = $this->finishedChallenge(survivors: 1, eliminated: 1, stake: 500_00);
        $winner = $challenge->participants()->where('status', 'active')->first()->user;
        $this->assertTrue($winner->hasOpenChallenges());

        $this->settle($challenge);

        $this->assertFalse($winner->fresh()->hasOpenChallenges());
        $this->assertSame(1000_00, $winner->fresh()->balance_available);
    }

    public function test_challenge_page_shows_results_after_settlement(): void
    {
        [$challenge, $users] = $this->finishedChallenge(survivors: 1, eliminated: 1, stake: 500_00);
        $this->settle($challenge);

        $this->actingAs($users->first())->get(route('challenges.show', $challenge))
            ->assertOk()->assertSee('Завершён')->assertSee('Дошёл до конца')->assertSee('получил(а)')->assertSee('Выбыл');
    }
}
