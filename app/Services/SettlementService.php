<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\CheckIn;
use Illuminate\Support\Facades\DB;

/**
 * End-of-challenge payouts. One atomic transaction per challenge, safe to run repeatedly.
 */
class SettlementService
{
    public function __construct(private WalletService $wallet, private ChallengeService $challenges) {}

    /**
     * Settle every active challenge whose last day is over and whose check-ins are all resolved.
     *
     * @return int number of challenges settled
     */
    public function settleDue(): int
    {
        $ids = Challenge::where('status', 'active')->where('end_date', '<', $this->challenges->today())->pluck('id');
        $settled = 0;

        foreach ($ids as $id) {
            $settled += $this->settle($id) ? 1 : 0;
        }

        return $settled;
    }

    /**
     * @return bool whether the challenge was settled by this call
     */
    public function settle(int $challengeId): bool
    {
        return DB::transaction(function () use ($challengeId) {
            $challenge = Challenge::whereKey($challengeId)->lockForUpdate()->first();

            if ($challenge?->status !== 'active' || $challenge->end_date->toDateString() >= $this->challenges->today()) {
                return false;
            }

            $participants = $challenge->participants()->whereIn('status', ['active', 'eliminated'])->with('user')->get();

            // A check-in that is still waiting to be marked as missed could eliminate someone: try again later.
            $unresolved = CheckIn::where('status', 'pending')
                ->where('date', '<=', $challenge->end_date)
                ->whereIn('user_id', $participants->where('status', 'active')->pluck('user_id'))
                ->exists();

            if ($unresolved) {
                return false;
            }

            $eliminated = $participants->where('status', 'eliminated');
            $survivors = $participants->where('status', 'active');
            $pool = $eliminated->count() * $challenge->stake_amount;

            // TODO(open decision #1): if every participant was eliminated, the platform keeps the pool for now.
            $share = $survivors->isEmpty() ? 0 : intdiv($pool, $survivors->count());

            foreach ($eliminated as $participant) {
                $this->wallet->forfeitStake($participant->user, $challenge);
            }

            foreach ($survivors as $participant) {
                $payout = $this->wallet->payOut($participant->user, $challenge, $share);
                $participant->update(['status' => 'survived', 'payout_amount' => $payout]);
            }

            // Whole kopecks only: what is left of the pool after the equal split stays with the platform.
            $challenge->update(['status' => 'completed', 'forfeit_pool' => $pool, 'survivor_share' => $share]);

            return true;
        });
    }
}
