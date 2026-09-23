<?php

namespace App\Providers;

use App\Contracts\PrayerTimeProvider;
use App\Services\PrayerTimes\LocalPrayerTimeCalculator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PrayerTimeProvider::class, LocalPrayerTimeCalculator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
