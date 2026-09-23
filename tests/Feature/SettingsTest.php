<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings')->assertOk();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'city_id' => $user->city_id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_changing_city_also_changes_timezone(): void
    {
        $user = User::factory()->create();
        $astrakhan = City::factory()->create(['timezone' => 'Europe/Astrakhan']);

        $this->actingAs($user)
            ->patch('/settings', [
                'name' => $user->name,
                'email' => $user->email,
                'city_id' => $astrakhan->id,
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame($astrakhan->id, $user->city_id);
        $this->assertSame('Europe/Astrakhan', $user->timezone);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_unknown_city_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings', [
                'name' => $user->name,
                'email' => $user->email,
                'city_id' => 999999,
            ])
            ->assertSessionHasErrors('city_id');
    }
}
