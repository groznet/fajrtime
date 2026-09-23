<?php

namespace Tests\Feature;

use App\Contracts\PrayerTimeProvider;
use App\Models\City;
use App\Services\PrayerTimes\LocalPrayerTimeCalculator;
use App\Services\PrayerTimeService;
use App\Support\PrayerTimes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrayerTimeServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $calls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Count how often the real calculator runs.
        $this->app->bind(PrayerTimeProvider::class, fn () => new class($this->calls) implements PrayerTimeProvider
        {
            public function __construct(private int &$calls) {}

            public function calculate(City $city, string $date): PrayerTimes
            {
                $this->calls++;

                return (new LocalPrayerTimeCalculator)->calculate($city, $date);
            }
        });
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_times_are_cached_per_city_per_day(): void
    {
        $service = app(PrayerTimeService::class);
        $grozny = City::factory()->create();
        $other = City::factory()->create();

        $first = $service->forDate($grozny, '2026-03-20');
        $second = $service->forDate($grozny, '2026-03-20');
        $this->assertSame(1, $this->calls);
        $this->assertTrue($first->fajr->equalTo($second->fajr));

        $service->forDate($grozny, '2026-03-21');
        $service->forDate($other, '2026-03-20');
        $this->assertSame(3, $this->calls);
    }

    public function test_today_uses_the_city_local_date(): void
    {
        $city = City::factory()->create(['timezone' => 'Europe/Moscow']);

        // 22:30 UTC on the 19th is already the 20th in Moscow.
        CarbonImmutable::setTestNow('2026-03-19 22:30:00');

        $this->assertSame('2026-03-20', app(PrayerTimeService::class)->today($city)->date);
    }

    public function test_next_fajr_is_today_before_fajr_and_tomorrow_after(): void
    {
        $city = City::factory()->create();
        $service = app(PrayerTimeService::class);

        // Grozny Fajr on 2026-03-20 is 04:37 Moscow time (01:37 UTC).
        CarbonImmutable::setTestNow('2026-03-20 00:00:00');
        $this->assertSame('2026-03-20 01:37', $service->nextFajr($city)->format('Y-m-d H:i'));

        CarbonImmutable::setTestNow('2026-03-20 02:00:00');
        $this->assertSame('2026-03-21', $service->nextFajr($city)->setTimezone('Europe/Moscow')->toDateString());
    }
}
