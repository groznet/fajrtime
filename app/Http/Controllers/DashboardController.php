<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Services\CheckInService;
use App\Services\PrayerTimeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, PrayerTimeService $prayerTimes, CheckInService $checkIns): View
    {
        $user = $request->user();
        $city = $user->city;

        return view('dashboard.index', [
            'user' => $user,
            'checkIn' => $user->hasRunningChallenge() ? $checkIns->state($user) : null,
            'participations' => $user->participations()
                ->where('status', 'active')
                ->whereHas('challenge', fn ($q) => $q->whereIn('status', Challenge::OPEN))
                ->with('challenge.group')
                ->get(),
            'city' => $city,
            'times' => $city ? $prayerTimes->today($city) : null,
            'nextFajr' => $city ? $prayerTimes->nextFajr($city)?->setTimezone($user->timezone) : null,
        ]);
    }
}
