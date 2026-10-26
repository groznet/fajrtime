<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'cities' => City::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:150', 'unique:'.User::class],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // Consent to personal data processing is given separately from the terms (152-FZ art. 9 part 1).
            'terms' => ['accepted'],
            'consent' => ['accepted'],
        ], [
            'terms.accepted' => 'Необходимо принять пользовательское соглашение и публичную оферту.',
            'consent.accepted' => 'Без согласия на обработку персональных данных регистрация невозможна.',
        ]);

        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'terms_accepted_at' => now(),
            'personal_data_consent_at' => now(),
        ]);
        $user->moveToCity(City::findOrFail($request->city_id));
        $user->save();

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
