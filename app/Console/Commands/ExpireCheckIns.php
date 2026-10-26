<?php

namespace App\Console\Commands;

use App\Services\CheckInService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('checkins:expire')]
#[Description('Mark unconfirmed check-ins past their deadline as missed and eliminate the users')]
class ExpireCheckIns extends Command
{
    public function handle(CheckInService $checkIns): int
    {
        $this->info('Missed check-ins: '.$checkIns->expireDue());

        return self::SUCCESS;
    }
}
