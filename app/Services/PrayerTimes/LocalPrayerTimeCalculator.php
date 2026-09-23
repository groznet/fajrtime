<?php

namespace App\Services\PrayerTimes;

use App\Contracts\PrayerTimeProvider;
use App\Models\City;
use App\Support\PrayerTimes;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Astronomical prayer time calculation (same formulas as PrayTimes.org).
 */
class LocalPrayerTimeCalculator implements PrayerTimeProvider
{
    // Sun's upper limb on the horizon, including refraction.
    private const RISE_SET_ANGLE = 0.833;

    private float $julianDate;

    private float $latitude;

    public function calculate(City $city, string $date): PrayerTimes
    {
        $method = config("prayer.methods.{$city->calc_method}")
            ?? throw new InvalidArgumentException("Unknown prayer calculation method [{$city->calc_method}].");

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');
        $this->latitude = $city->latitude;
        $this->julianDate = $this->julian($day->year, $day->month, $day->day) - $city->longitude / (15 * 24);
        $asrFactor = $city->asr_method === 'hanafi' ? 2 : 1;

        // Hours in local solar time. Start from rough guesses and refine them.
        $times = ['fajr' => 5, 'sunrise' => 6, 'dhuhr' => 12, 'asr' => 13, 'maghrib' => 18, 'isha' => 18];

        for ($i = 0; $i < 2; $i++) {
            $p = array_map(fn ($hours) => $hours / 24, $times);

            $times = [
                'fajr' => $this->sunAngleTime($method['fajr_angle'], $p['fajr'], true),
                'sunrise' => $this->sunAngleTime(self::RISE_SET_ANGLE, $p['sunrise'], true),
                'dhuhr' => $this->midDay($p['dhuhr']),
                'asr' => $this->asrTime($asrFactor, $p['asr']),
                'maghrib' => $this->sunAngleTime(self::RISE_SET_ANGLE, $p['maghrib'], false),
                'isha' => $this->sunAngleTime($method['isha_angle'], $p['isha'], false),
            ];
        }

        $utc = [];

        foreach ($times as $name => $hours) {
            if (is_nan($hours)) {
                $utc[$name] = null;

                continue;
            }

            $minutes = (int) round(($hours - $city->longitude / 15) * 60) + ($method['offsets'][$name] ?? 0);
            $utc[$name] = $day->addMinutes($minutes);
        }

        return new PrayerTimes($date, ...$utc);
    }

    private function julian(int $year, int $month, int $day): float
    {
        if ($month <= 2) {
            $year--;
            $month += 12;
        }

        $a = floor($year / 100);
        $b = 2 - $a + floor($a / 4);

        return floor(365.25 * ($year + 4716)) + floor(30.6001 * ($month + 1)) + $day + $b - 1524.5;
    }

    /**
     * @return array{0: float, 1: float} [declination in degrees, equation of time in hours]
     */
    private function sunPosition(float $jd): array
    {
        $d = $jd - 2451545.0;
        $g = $this->fixAngle(357.529 + 0.98560028 * $d);
        $q = $this->fixAngle(280.459 + 0.98564736 * $d);
        $l = $this->fixAngle($q + 1.915 * $this->sin($g) + 0.020 * $this->sin(2 * $g));
        $e = 23.439 - 0.00000036 * $d;

        $rightAscension = rad2deg(atan2($this->cos($e) * $this->sin($l), $this->cos($l))) / 15;
        $equation = $q / 15 - $this->fixHour($rightAscension);
        $declination = rad2deg(asin($this->sin($e) * $this->sin($l)));

        return [$declination, $equation];
    }

    private function midDay(float $dayPortion): float
    {
        [, $equation] = $this->sunPosition($this->julianDate + $dayPortion);

        return $this->fixHour(12 - $equation);
    }

    /**
     * Time when the sun is $angle degrees below the horizon, before noon ($beforeNoon) or after.
     * Returns NAN if the sun never reaches that angle on this day.
     */
    private function sunAngleTime(float $angle, float $dayPortion, bool $beforeNoon): float
    {
        [$declination] = $this->sunPosition($this->julianDate + $dayPortion);
        $noon = $this->midDay($dayPortion);

        $cosT = (-$this->sin($angle) - $this->sin($declination) * $this->sin($this->latitude))
            / ($this->cos($declination) * $this->cos($this->latitude));

        if ($cosT < -1 || $cosT > 1) {
            return NAN;
        }

        $t = rad2deg(acos($cosT)) / 15;

        return $beforeNoon ? $noon - $t : $noon + $t;
    }

    private function asrTime(int $shadowFactor, float $dayPortion): float
    {
        [$declination] = $this->sunPosition($this->julianDate + $dayPortion);
        $angle = -rad2deg(atan(1 / ($shadowFactor + $this->tan(abs($this->latitude - $declination)))));

        return $this->sunAngleTime($angle, $dayPortion, false);
    }

    private function sin(float $degrees): float
    {
        return sin(deg2rad($degrees));
    }

    private function cos(float $degrees): float
    {
        return cos(deg2rad($degrees));
    }

    private function tan(float $degrees): float
    {
        return tan(deg2rad($degrees));
    }

    private function fixAngle(float $a): float
    {
        return $a - 360 * floor($a / 360);
    }

    private function fixHour(float $h): float
    {
        return $h - 24 * floor($h / 24);
    }
}
