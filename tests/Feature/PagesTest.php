<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public static function publicPages(): array
    {
        return [['/'], ['/how-it-works'], ['/faq'], ['/contact'], ['/terms'], ['/privacy'], ['/offer']];
    }

    #[DataProvider('publicPages')]
    public function test_public_pages_render(string $url): void
    {
        $this->get($url)->assertOk();
    }

    public function test_prayer_times_page_shows_selected_city(): void
    {
        CarbonImmutable::setTestNow('2026-03-20 00:00:00');
        City::factory()->create(['name' => 'Первый']);
        $grozny = City::factory()->create(['name' => 'Грозный']);

        $this->get('/prayer-times?city='.$grozny->id)
            ->assertOk()
            ->assertSee('Грозный, 20 марта 2026')
            ->assertSee('04:37');
    }

    public function test_prayer_times_page_defaults_to_the_users_city(): void
    {
        City::factory()->create(['name' => 'Первый']);
        $user = User::factory()->for(City::factory()->state(['name' => 'Мой город']))->create();

        $this->actingAs($user)->get('/prayer-times')->assertSee('Мой город,');
    }

    public function test_dashboard_shows_next_fajr_in_user_timezone(): void
    {
        CarbonImmutable::setTestNow('2026-03-20 00:00:00');
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Следующий Фаджр')
            ->assertSee('04:37');
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
