<?php

namespace App\Contracts;

use App\Models\City;
use App\Support\PrayerTimes;

interface PrayerTimeProvider
{
    /**
     * Prayer times for a city on a local calendar date (Y-m-d). All returned times are UTC.
     */
    public function calculate(City $city, string $date): PrayerTimes;
}
