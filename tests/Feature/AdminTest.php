<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\Challenge;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\SettlementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function withdrawal(int $amount = 300_00, array $userState = []): Withdrawal
    {
        // The money already left the balance when the request was made.
        $user = User::factory()->create($userState + ['balance_available' => 200_00]);

        return $user->withdrawals()->create([
            'amount' => $amount,
            'details' => ['phone' => '+79990001122', 'bank' => 'Сбербанк'],
        ]);
    }

    // --- access -------------------------------------------------------------------------------

    public function test_admin_area_is_only_for_admins(): void
    {
        $withdrawal = $this->withdrawal();
        $user = User::factory()->create();

        foreach (['/admin', '/admin/withdrawals'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post(route('admin.withdrawals.pay', $withdrawal))->assertRedirect('/login');

        foreach (['/admin', '/admin/withdrawals'] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }

        $this->actingAs($user)->post(route('admin.withdrawals.pay', $withdrawal))->assertForbidden();
        $this->actingAs($user)->post(route('admin.withdrawals.reject', $withdrawal), ['reason' => 'нет'])->assertForbidden();
        $this->assertSame('requested', $withdrawal->fresh()->status);

        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    public function test_admin_link_is_shown_only_to_admins(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee(route('admin.index'));
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertSee(route('admin.index'));
    }

    // --- withdrawals --------------------------------------------------------------------------

    public function test_pending_withdrawals_are_listed_first_with_payout_details(): void
    {
        $paid = $this->withdrawal(100_00);
        $paid->update(['status' => 'paid']);
        $pending = $this->withdrawal(777_00);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/withdrawals')
            ->assertOk()
            ->assertSeeInOrder(['777', 'Ожидает', '100', 'Выплачена'])
            ->assertSee('+79990001122')->assertSee('Сбербанк');
        $this->get('/admin')->assertSee('Ожидают обработки');
    }

    public function test_marking_a_withdrawal_paid_is_logged_and_does_not_touch_the_balance(): void
    {
        $withdrawal = $this->withdrawal();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.withdrawals.pay', $withdrawal))->assertRedirect();

        $withdrawal->refresh();
        $this->assertSame('paid', $withdrawal->status);
        $this->assertNotNull($withdrawal->processed_at);
        $this->assertSame(200_00, $withdrawal->user->fresh()->balance_available);
        $this->assertSame(0, Transaction::count());
        $this->assertDatabaseHas('admin_actions', [
            'admin_id' => $admin->id, 'action' => 'withdrawal.paid', 'subject_type' => 'Withdrawal', 'subject_id' => $withdrawal->id,
        ]);
    }

    public function test_rejecting_a_withdrawal_refunds_the_money_through_the_ledger(): void
    {
        $withdrawal = $this->withdrawal(300_00);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.withdrawals.reject', $withdrawal), ['reason' => 'Неверные реквизиты'])->assertRedirect();

        $this->assertSame('rejected', $withdrawal->fresh()->status);
        $this->assertSame(500_00, $withdrawal->user->fresh()->balance_available);
        $row = Transaction::firstOrFail();
        $this->assertSame(['adjustment', 'available', 300_00, $withdrawal->id], [$row->type, $row->affects, $row->amount, $row->withdrawal_id]);
        $this->assertSame('Неверные реквизиты', AdminAction::firstWhere('action', 'withdrawal.rejected')->reason);
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $withdrawal = $this->withdrawal();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.withdrawals.reject', $withdrawal), ['reason' => ''])->assertSessionHasErrors('reason');

        $this->assertSame('requested', $withdrawal->fresh()->status);
        $this->assertSame(200_00, $withdrawal->user->fresh()->balance_available);
        $this->assertSame(0, AdminAction::count());
    }

    public function test_a_withdrawal_cannot_be_processed_twice(): void
    {
        $withdrawal = $this->withdrawal();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.withdrawals.reject', $withdrawal), ['reason' => 'Ошибка']);
        $this->post(route('admin.withdrawals.reject', $withdrawal), ['reason' => 'Ещё раз'])->assertSessionHasErrors('withdrawal');
        $this->post(route('admin.withdrawals.pay', $withdrawal))->assertSessionHasErrors('withdrawal');

        // Refunded exactly once, nothing marked paid, no extra log rows.
        $this->assertSame(500_00, $withdrawal->user->fresh()->balance_available);
        $this->assertSame('rejected', $withdrawal->fresh()->status);
        $this->assertSame(1, AdminAction::count());
    }

    // --- restoring a participant ----------------------------------------------------------------

    private function eliminated(string $challengeStatus = 'active'): array
    {
        $challenge = Challenge::factory()->create([
            'status' => $challengeStatus,
            'start_date' => '2026-03-04',
            'end_date' => '2026-03-10',
        ]);
        $user = User::factory()->create(['balance_available' => 0, 'balance_locked' => 500_00]);
        $participant = $challenge->participants()->create(['user_id' => $user->id, 'status' => 'eliminated', 'eliminated_on' => '2026-03-06']);

        return [$challenge, $user, $participant];
    }

    public function test_admin_restores_an_eliminated_participant_with_a_logged_reason(): void
    {
        [$challenge, $user, $participant] = $this->eliminated();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.challenges.show', $challenge))->assertOk()->assertSee('Вернуть в челлендж');
        $this->post(route('admin.participants.restore', $participant), ['reason' => 'Сбой сайта 6 марта'])->assertRedirect();

        $participant->refresh();
        $this->assertSame('active', $participant->status);
        $this->assertNull($participant->eliminated_on);
        // No money moved: the stake was still locked.
        $this->assertSame([0, 500_00], [$user->fresh()->balance_available, $user->fresh()->balance_locked]);
        $this->assertSame(0, Transaction::count());
        $this->assertDatabaseHas('admin_actions', [
            'admin_id' => $admin->id, 'action' => 'participant.restored', 'subject_type' => 'ChallengeParticipant',
            'subject_id' => $participant->id, 'reason' => 'Сбой сайта 6 марта',
        ]);
    }

    public function test_restoring_requires_a_reason(): void
    {
        [, , $participant] = $this->eliminated();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.participants.restore', $participant), ['reason' => 'ок'])->assertSessionHasErrors('reason');

        $this->assertSame('eliminated', $participant->fresh()->status);
        $this->assertSame(0, AdminAction::count());
    }

    public function test_only_eliminated_participants_of_a_running_challenge_can_be_restored(): void
    {
        $admin = User::factory()->admin()->create();
        [, , $inCompleted] = $this->eliminated('completed');
        [$challenge, , $active] = $this->eliminated();
        $active->update(['status' => 'active']);

        $this->actingAs($admin)->post(route('admin.participants.restore', $inCompleted), ['reason' => 'Слишком поздно'])->assertSessionHasErrors('participant');
        $this->post(route('admin.participants.restore', $active), ['reason' => 'Он и так в игре'])->assertSessionHasErrors('participant');

        $this->assertSame('eliminated', $inCompleted->fresh()->status);
        $this->assertSame(0, AdminAction::count());
    }

    public function test_a_participant_who_joined_another_challenge_cannot_be_restored(): void
    {
        [, $user, $participant] = $this->eliminated();
        $other = Challenge::factory()->create(['status' => 'recruiting']);
        $other->participants()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.participants.restore', $participant), ['reason' => 'Сбой сайта'])->assertSessionHasErrors('participant');

        $this->assertSame('eliminated', $participant->fresh()->status);
    }

    public function test_a_restored_participant_is_paid_out_as_a_survivor(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-11 07:00:00', 'UTC'));
        [$challenge, $user, $participant] = $this->eliminated();
        $other = User::factory()->create(['balance_available' => 0, 'balance_locked' => 500_00]);
        $challenge->participants()->create(['user_id' => $other->id, 'status' => 'active']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.participants.restore', $participant), ['reason' => 'Неверное время Фаджра']);
        app(SettlementService::class)->settle($challenge->id);

        // Nobody is eliminated any more: both get exactly their stake back.
        $this->assertSame(500_00, $user->fresh()->balance_available);
        $this->assertSame(500_00, $other->fresh()->balance_available);
        $this->assertSame(0, $challenge->fresh()->forfeit_pool);
    }

    public function test_action_log_is_shown_on_the_admin_pages(): void
    {
        [$challenge, , $participant] = $this->eliminated();
        $admin = User::factory()->admin()->create(['name' => 'Админ Иван']);

        $this->actingAs($admin)->post(route('admin.participants.restore', $participant), ['reason' => 'Сбой хостинга']);

        $this->get('/admin')->assertSee('Участник возвращён в челлендж')->assertSee('Сбой хостинга')->assertSee('Админ Иван');
        $this->get(route('admin.challenges.show', $challenge))->assertSee('Сбой хостинга');
    }
}
