<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\PrayerTimeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrayerTimeController extends Controller
{
    public function index(Request $request, PrayerTimeService $prayerTimes): View
    {
        $cities = City::orderBy('name')->get();

        $city = $cities->firstWhere('id', (int) $request->query('city'))
            ?? $request->user()?->city
            ?? $cities->sortBy('id')->first();

        abort_if($city === null, 404);

        return view('pages.prayer-times', [
            'cities' => $cities->pluck('name', 'id'),
            'city' => $city,
            'today' => CarbonImmutable::now($city->timezone),
            'times' => $prayerTimes->today($city),
            'methodName' => config("prayer.methods.{$city->calc_method}.name"),
        ]);
    }
}
