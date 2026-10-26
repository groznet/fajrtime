<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * The only place that changes user balances. Every change writes a ledger row
 * and updates the cached users.balance_* columns in the same DB transaction.
 */
class WalletService
{
    /**
     * Credit a succeeded top-up. Safe to call more than once for the same payment.
     */
    public function creditTopUp(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'succeeded') {
                return;
            }

            $payment->update(['status' => 'succeeded', 'paid_at' => now()]);

            $this->record($payment->user_id, 'topup', $payment->amount_net, 'available', [
                'payment_id' => $payment->id,
                'description' => 'Пополнение картой',
            ]);
        });
    }

    /**
     * Take the amount off the available balance and create a withdrawal request.
     */
    public function requestWithdrawal(User $user, int $amount, array $details): Withdrawal
    {
        return DB::transaction(function () use ($user, $amount, $details) {
            $locked = $this->lockUser($user->id);

            if ($locked->hasOpenChallenges()) {
                throw ValidationException::withMessages(['amount' => 'Вывод недоступен, пока вы участвуете в челлендже.']);
            }

            if ($locked->withdrawals()->where('status', 'requested')->exists()) {
                throw ValidationException::withMessages(['amount' => 'У вас уже есть заявка на вывод в обработке.']);
            }

            if ($locked->balance_available < $amount) {
                throw ValidationException::withMessages(['amount' => 'Недостаточно средств на балансе.']);
            }

            $withdrawal = $locked->withdrawals()->create([
                'amount' => $amount,
                'details' => $details,
            ]);

            $this->record($locked->id, 'withdrawal', -$amount, 'available', [
                'withdrawal_id' => $withdrawal->id,
                'description' => 'Заявка на вывод',
            ]);

            return $withdrawal;
        });
    }

    /**
     * Move the stake from the available to the locked balance when joining a challenge.
     */
    public function lockStake(User $user, Challenge $challenge): void
    {
        DB::transaction(function () use ($user, $challenge) {
            $locked = $this->lockUser($user->id);

            if ($locked->balance_available < $challenge->stake_amount) {
                throw ValidationException::withMessages(['balance' => 'Недостаточно средств на балансе.']);
            }

            $this->record($user->id, 'stake_lock', -$challenge->stake_amount, 'available', [
                'challenge_id' => $challenge->id,
                'description' => 'Ставка за вступление в челлендж',
            ]);
            $this->record($user->id, 'stake_lock', $challenge->stake_amount, 'locked', [
                'challenge_id' => $challenge->id,
                'description' => 'Ставка за вступление в челлендж',
            ]);
        });
    }

    /**
     * Return a locked stake to the available balance (left the challenge, or it was cancelled).
     */
    public function unlockStake(User $user, Challenge $challenge): void
    {
        DB::transaction(function () use ($user, $challenge) {
            $this->record($user->id, 'stake_unlock', -$challenge->stake_amount, 'locked', [
                'challenge_id' => $challenge->id,
                'description' => 'Ставка возвращена',
            ]);
            $this->record($user->id, 'stake_unlock', $challenge->stake_amount, 'available', [
                'challenge_id' => $challenge->id,
                'description' => 'Ставка возвращена',
            ]);
        });
    }

    /**
     * Write a ledger row and update the cached balance. Must run inside a DB transaction.
     */
    private function record(int $userId, string $type, int $amount, string $affects, array $attributes = []): Transaction
    {
        $user = $this->lockUser($userId);
        $column = 'balance_'.$affects;

        if ($user->{$column} + $amount < 0) {
            throw new LogicException("Balance {$column} of user {$userId} would go negative.");
        }

        $user->{$column} += $amount;
        $user->save();

        return Transaction::create([
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'affects' => $affects,
            ...$attributes,
        ]);
    }

    private function lockUser(int $userId): User
    {
        return User::whereKey($userId)->lockForUpdate()->firstOrFail();
    }
}
