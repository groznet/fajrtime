<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\CommissionService;
use Illuminate\View\View;

class HowItWorksController extends Controller
{
    private const PARTICIPANTS = 30;

    private const ELIMINATED = 10;

    private const TOP_UP = 500_00;

    /**
     * The worked example is computed from the live commission, so the page cannot go stale.
     */
    public function __invoke(CommissionService $commission): View
    {
        $stake = $commission->netFor(self::TOP_UP);
        $survivors = self::PARTICIPANTS - self::ELIMINATED;
        $pool = self::ELIMINATED * $stake;
        $share = intdiv($pool, $survivors);

        return view('pages.how-it-works', [
            'percent' => rtrim(rtrim(number_format($commission->percent(), 2, ',', ''), '0'), ','),
            'example' => [
                'participants' => self::PARTICIPANTS,
                'eliminated' => self::ELIMINATED,
                'survivors' => $survivors,
                'top_up' => self::TOP_UP,
                'commission' => $commission->commissionFor(self::TOP_UP),
                'stake' => $stake,
                'pool' => $pool,
                'share' => $share,
                'payout' => $stake + $share,
            ],
            'stakeMin' => Setting::int('stake_min'),
            'stakeMax' => Setting::int('stake_max'),
            'minParticipants' => Setting::int('min_participants'),
            'revealLength' => Setting::int('reveal_range_length_min'),
            'codeValid' => Setting::int('code_valid_min'),
            'maxAttempts' => Setting::int('checkin_max_attempts'),
        ]);
    }
}
