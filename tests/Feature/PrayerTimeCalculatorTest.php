<?php

namespace Tests\Feature;

use App\Models\City;
use App\Services\PrayerTimes\LocalPrayerTimeCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Reference values: api.aladhan.com, method 14 (Spiritual Administration of Muslims of Russia).
 */
class PrayerTimeCalculatorTest extends TestCase
{
    public static function referenceTimes(): array
    {
        $grozny = [43.318700, 45.698100, 'Europe/Moscow', 'shafii'];
        $astrakhanHanafi = [46.347900, 48.033600, 'Europe/Astrakhan', 'hanafi'];

        return [
            'Grozny, equinox' => [$grozny, '2026-03-20', ['fajr' => '04:37', 'sunrise' => '06:01', 'dhuhr' => '12:05', 'asr' => '15:28', 'maghrib' => '18:09', 'isha' => '19:28']],
            'Grozny, summer solstice' => [$grozny, '2026-06-21', ['fajr' => '02:18', 'sunrise' => '04:17', 'dhuhr' => '11:59', 'asr' => '16:05', 'maghrib' => '19:41', 'isha' => '21:30']],
            'Astrakhan, winter, hanafi' => [$astrakhanHanafi, '2026-12-21', ['fajr' => '06:50', 'sunrise' => '08:28', 'dhuhr' => '12:46', 'asr' => '15:20', 'maghrib' => '17:04', 'isha' => '18:36']],
        ];
    }

    #[DataProvider('referenceTimes')]
    public function test_matches_reference_timetable_within_one_minute(array $place, string $date, array $expected): void
    {
        [$latitude, $longitude, $timezone, $asr] = $place;
        $city = new City([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'calc_method' => 'russia',
            'asr_method' => $asr,
        ]);

        $times = (new LocalPrayerTimeCalculator)->calculate($city, $date);

        $this->assertSame($date, $times->date);

        foreach ($expected as $name => $hhmm) {
            $actual = $times->get($name);
            $reference = $actual->setTimezone($timezone)->setTimeFromTimeString($hhmm);

            $this->assertLessThanOrEqual(
                60,
                abs($actual->diffInSeconds($reference)),
                "{$name}: expected {$hhmm}, got {$times->local($name, $timezone)}",
            );
        }
    }

    public function test_times_are_returned_in_utc(): void
    {
        $city = City::factory()->make();

        $times = (new LocalPrayerTimeCalculator)->calculate($city, '2026-03-20');

        $this->assertSame('UTC', $times->fajr->timezoneName);
        $this->assertSame('01:37', $times->fajr->format('H:i'));
    }

    public function test_fajr_is_null_when_the_sun_does_not_reach_the_angle(): void
    {
        $moscow = City::factory()->make(['latitude' => 55.7558, 'longitude' => 37.6173]);

        $times = (new LocalPrayerTimeCalculator)->calculate($moscow, '2026-06-21');

        $this->assertNull($times->fajr);
        $this->assertNull($times->isha);
        $this->assertNotNull($times->sunrise);
        $this->assertSame('—', $times->local('fajr', 'Europe/Moscow'));
    }

    public function test_unknown_method_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new LocalPrayerTimeCalculator)->calculate(City::factory()->make(['calc_method' => 'nope']), '2026-03-20');
    }
}
