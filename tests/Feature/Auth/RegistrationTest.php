<?php

namespace Tests\Feature\Auth;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        City::factory()->create(['name' => 'Грозный']);

        $this->get('/register')->assertOk()->assertSee('Грозный');
    }

    public function test_new_users_can_register_with_a_city(): void
    {
        $city = City::factory()->create(['timezone' => 'Europe/Astrakhan']);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'city_id' => $city->id,
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
            'consent' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::firstWhere('email', 'test@example.com');
        $this->assertSame($city->id, $user->city_id);
        $this->assertSame('Europe/Astrakhan', $user->timezone);
        $this->assertSame('user', $user->fresh()->role);
        $this->assertSame(0, $user->fresh()->balance_available);
    }

    public function test_terms_and_personal_data_consent_are_separate_required_checkboxes(): void
    {
        $city = City::factory()->create();
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'city_id' => $city->id,
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $this->post('/register', $payload + ['consent' => '1'])->assertSessionHasErrors('terms');
        $this->post('/register', $payload + ['terms' => '1'])->assertSessionHasErrors('consent');
        $this->assertGuest();

        $this->post('/register', $payload + ['terms' => '1', 'consent' => '1']);
        $user = User::firstWhere('email', 'test@example.com');
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertNotNull($user->personal_data_consent_at);
    }

    public function test_city_is_required(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
            'consent' => '1',
        ])->assertSessionHasErrors('city_id');

        $this->assertGuest();
    }

    public function test_role_and_balance_cannot_be_mass_assigned_on_registration(): void
    {
        $city = City::factory()->create();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'city_id' => $city->id,
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
            'consent' => '1',
            'role' => 'admin',
            'balance_available' => 100000,
        ]);

        $user = User::firstWhere('email', 'test@example.com');
        $this->assertSame('user', $user->role);
        $this->assertSame(0, $user->balance_available);
    }
}
