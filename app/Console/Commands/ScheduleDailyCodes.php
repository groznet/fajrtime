<?php

namespace App\Console\Commands;

use App\Services\CheckInService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('checkins:schedule')]
#[Description('Create check-in rows (random reveal time and code) for users whose Fajr is coming up')]
class ScheduleDailyCodes extends Command
{
    public function handle(CheckInService $checkIns): int
    {
        $this->info('Created check-ins: '.$checkIns->scheduleDue());

        return self::SUCCESS;
    }
}
