<?php

namespace App\Console\Commands;

use App\Services\SettlementService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('challenges:settle')]
#[Description('Pay out finished challenges: survivors get their stake plus an equal share of the forfeit pool')]
class SettleFinishedChallenges extends Command
{
    public function handle(SettlementService $settlement): int
    {
        $this->info('Settled challenges: '.$settlement->settleDue());

        return self::SUCCESS;
    }
}
