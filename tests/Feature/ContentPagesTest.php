<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Group;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_how_it_works_shows_the_spec_example_with_the_default_commission(): void
    {
        // 500 ₽ top-up, 5% commission -> stake 475 ₽; 10 of 30 eliminated -> pool 4 750 ₽, 237,50 ₽ each, 712,50 ₽ payout.
        $this->get('/how-it-works')->assertOk()
            ->assertSee('Пример расчёта')
            ->assertSeeInOrder(['475', "4\u{00A0}750", '237,50', '712,50'], escape: false)
            ->assertSee('Частые вопросы');
    }

    public function test_the_example_follows_the_configured_commission(): void
    {
        Setting::create(['key' => 'commission_percent', 'value' => '10']);

        // 10% of 500 ₽ = 50 ₽ -> stake 450 ₽, pool 4 500 ₽, share 225 ₽, payout 675 ₽.
        $this->get('/how-it-works')->assertOk()->assertSee('комиссия 10%')->assertSee('675')->assertDontSee('712,50');
    }

    public function test_faq_is_part_of_how_it_works_and_has_no_page_of_its_own(): void
    {
        $this->get('/faq')->assertNotFound();
        $this->get('/how-it-works')->assertSee('id="faq"', escape: false);
    }

    public function test_home_links_to_the_catalog_and_the_rules(): void
    {
        $this->get('/')->assertOk()->assertSee(route('groups.index'))->assertSee(route('how-it-works'));
    }

    // --- one challenge at a time --------------------------------------------------------------

    private function recruitingFor(User $user): Challenge
    {
        $challenge = Challenge::factory()->create(['group_id' => Group::factory()]);
        $challenge->group->members()->attach($user->id);

        return $challenge;
    }

    public function test_a_user_cannot_join_a_second_open_challenge(): void
    {
        $user = User::factory()->create(['balance_available' => 2000_00]);
        $first = $this->recruitingFor($user);
        $second = $this->recruitingFor($user);

        $this->actingAs($user)->post(route('challenges.join', $first))->assertRedirect();
        $this->post(route('challenges.join', $second))->assertSessionHasErrors('challenge');

        $this->assertSame(500_00, $user->fresh()->balance_locked);
        $this->assertSame(0, $second->participants()->count());
    }

    public function test_the_rule_also_covers_a_running_challenge_and_lifts_afterwards(): void
    {
        $user = User::factory()->create(['balance_available' => 2000_00]);
        $first = $this->recruitingFor($user);
        $second = $this->recruitingFor($user);
        $this->actingAs($user)->post(route('challenges.join', $first));

        $first->update(['status' => 'active']);
        $this->post(route('challenges.join', $second))->assertSessionHasErrors('challenge');

        $first->update(['status' => 'completed']);
        $this->post(route('challenges.join', $second))->assertSessionHasNoErrors();
        $this->assertSame(1, $second->participants()->where('status', 'active')->count());
    }

    public function test_leaving_the_first_challenge_frees_the_user_to_join_another(): void
    {
        $user = User::factory()->create(['balance_available' => 2000_00]);
        $first = $this->recruitingFor($user);
        $second = $this->recruitingFor($user);

        $this->actingAs($user)->post(route('challenges.join', $first));
        $this->post(route('challenges.leave', $first));
        $this->post(route('challenges.join', $second))->assertSessionHasNoErrors();

        $this->assertSame(1, $second->participants()->where('status', 'active')->count());
    }

    public function test_eliminated_users_may_join_a_new_challenge(): void
    {
        $user = User::factory()->create(['balance_available' => 2000_00]);
        $first = $this->recruitingFor($user);
        $second = $this->recruitingFor($user);
        $first->participants()->create(['user_id' => $user->id, 'status' => 'eliminated']);
        $first->update(['status' => 'active']);

        $this->actingAs($user)->post(route('challenges.join', $second))->assertSessionHasNoErrors();
    }
}
