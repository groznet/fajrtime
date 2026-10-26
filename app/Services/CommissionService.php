<?php

namespace App\Services;

use App\Models\Setting;

class CommissionService
{
    public function percent(): float
    {
        return (float) Setting::get('commission_percent');
    }

    /**
     * Commission taken from a top-up, in kopecks (rounded to the nearest kopeck).
     */
    public function commissionFor(int $gross): int
    {
        return (int) round($gross * $this->percent() / 100);
    }

    /**
     * What is credited to the balance after commission.
     */
    public function netFor(int $gross): int
    {
        return $gross - $this->commissionFor($gross);
    }

    /**
     * Smallest top-up that credits at least $net to the balance.
     */
    public function grossFor(int $net): int
    {
        $gross = (int) ceil($net * 100 / (100 - $this->percent()));

        // Rounding of the commission can leave us a kopeck short.
        while ($this->netFor($gross) < $net) {
            $gross++;
        }

        return $gross;
    }
}
