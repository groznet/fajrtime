<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingsUpdateRequest;
use App\Models\City;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', [
            'user' => $request->user(),
            'cities' => City::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(SettingsUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email']));

        if ((int) $request->validated('city_id') !== $user->city_id) {
            if ($user->hasOpenChallenges()) {
                return back()->withInput()->withErrors(['city_id' => 'Город нельзя менять, пока вы участвуете в челлендже.']);
            }

            $user->moveToCity(City::findOrFail($request->validated('city_id')));
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return redirect()->route('settings.edit')->with('status', 'Настройки сохранены.');
    }
}
