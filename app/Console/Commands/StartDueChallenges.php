<?php

namespace App\Console\Commands;

use App\Services\ChallengeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('challenges:start-due')]
#[Description('Start recruiting challenges whose start date has come, or cancel them if there are too few participants')]
class StartDueChallenges extends Command
{
    public function handle(ChallengeService $challenges): int
    {
        $this->info('Processed challenges: '.$challenges->startDue());

        return self::SUCCESS;
    }
}
