<?php

namespace App\Services;

use App\Contracts\PrayerTimeProvider;
use App\Models\City;
use App\Support\PrayerTimes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class PrayerTimeService
{
    public function __construct(private PrayerTimeProvider $provider) {}

    /**
     * Prayer times for a local date (Y-m-d) in the city, cached per city per day.
     */
    public function forDate(City $city, string $date): PrayerTimes
    {
        $key = "prayer_times:{$city->id}:{$city->calc_method}:{$city->asr_method}:{$date}";

        $data = Cache::remember(
            $key,
            now()->addHours(config('prayer.cache_ttl_hours')),
            fn () => $this->provider->calculate($city, $date)->toArray(),
        );

        return PrayerTimes::fromArray($data);
    }

    public function today(City $city): PrayerTimes
    {
        return $this->forDate($city, CarbonImmutable::now($city->timezone)->toDateString());
    }

    /**
     * The next Fajr that has not started yet (today's or tomorrow's), in UTC.
     */
    public function nextFajr(City $city): ?CarbonImmutable
    {
        $today = CarbonImmutable::now($city->timezone);
        $fajr = $this->forDate($city, $today->toDateString())->fajr;

        if ($fajr !== null && $fajr->isFuture()) {
            return $fajr;
        }

        return $this->forDate($city, $today->addDay()->toDateString())->fajr;
    }
}
