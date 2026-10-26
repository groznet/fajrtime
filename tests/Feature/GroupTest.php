<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    private function groupPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ранний подъём',
            'description' => 'Встаём на Фаджр',
            'stake' => 500,
            'cycle_days' => 7,
            'visibility' => 'public',
        ], $overrides);
    }

    public function test_user_creates_a_group_and_becomes_its_member(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/my/groups', $this->groupPayload());

        $group = Group::firstOrFail();
        $response->assertRedirect(route('groups.show', $group));
        $this->assertSame(50_000, $group->stake_amount);
        $this->assertSame($user->id, $group->creator_id);
        $this->assertTrue($group->hasMember($user));
        $this->assertNotEmpty($group->slug);
        $this->assertSame(40, strlen($group->invite_token));
    }

    public function test_group_settings_are_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/my/groups', $this->groupPayload(['stake' => 50, 'cycle_days' => 8, 'visibility' => 'x']))
            ->assertSessionHasErrors(['stake', 'cycle_days', 'visibility']);
        $this->actingAs($user)->post('/my/groups', $this->groupPayload(['stake' => 10_001]))->assertSessionHasErrors('stake');

        $this->assertSame(0, Group::count());
    }

    public function test_groups_with_the_same_name_get_unique_slugs(): void
    {
        $user = User::factory()->create();
        $service = app(GroupService::class);
        $data = ['name' => 'Фаджр', 'stake_amount' => 100_00, 'cycle_days' => 7, 'visibility' => 'public'];

        $a = $service->create($user, $data);
        $b = $service->create($user, $data);

        $this->assertNotSame($a->slug, $b->slug);
    }

    public function test_catalog_lists_only_public_groups_and_filters(): void
    {
        Group::factory()->create(['name' => 'Открытая семидневка', 'cycle_days' => 7]);
        Group::factory()->create(['name' => 'Открытая месячная', 'cycle_days' => 30]);
        Group::factory()->private()->create(['name' => 'Секретная']);

        $this->get('/groups')->assertSee('Открытая семидневка')->assertSee('Открытая месячная')->assertDontSee('Секретная');
        $this->get('/groups?cycle_days=30')->assertSee('Открытая месячная')->assertDontSee('Открытая семидневка');
        $this->get('/groups?stake_from=600')->assertDontSee('Открытая месячная');
    }

    public function test_private_group_is_hidden_without_invite_but_visible_through_it(): void
    {
        $group = Group::factory()->private()->create();
        $outsider = User::factory()->create();

        $this->get(route('groups.show', $group))->assertNotFound();
        $this->actingAs($outsider)->get(route('groups.show', $group))->assertNotFound();
        $this->get(route('groups.invite', $group->invite_token))->assertOk()->assertSee($group->name);
        $this->get(route('groups.invite', 'wrong-token'))->assertNotFound();
    }

    public function test_joining_a_private_group_needs_the_invite_token(): void
    {
        $group = Group::factory()->private()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('groups.join', $group))->assertNotFound();
        $this->assertFalse($group->hasMember($user));

        $this->actingAs($user)->post(route('groups.join', $group), ['invite' => $group->invite_token])->assertRedirect();
        $this->assertTrue($group->hasMember($user));
    }

    public function test_member_can_leave_but_creator_cannot(): void
    {
        $group = Group::factory()->create();
        $group->members()->attach($group->creator_id);
        $member = User::factory()->create();
        $group->members()->attach($member->id);

        $this->actingAs($member)->post(route('groups.leave', $group))->assertRedirect(route('groups.mine'));
        $this->assertFalse($group->hasMember($member));

        $this->actingAs($group->creator)->post(route('groups.leave', $group))->assertSessionHasErrors('group');
        $this->assertTrue($group->hasMember($group->creator));
    }

    public function test_member_cannot_leave_a_group_while_in_its_challenge(): void
    {
        $challenge = Challenge::factory()->create();
        $group = $challenge->group;
        $user = User::factory()->create(['balance_available' => 500_00]);
        $group->members()->attach($user->id);
        $this->actingAs($user)->post(route('challenges.join', $challenge));

        $this->post(route('groups.leave', $group))->assertSessionHasErrors('group');
        $this->assertTrue($group->hasMember($user));
    }

    public function test_guests_cannot_create_groups(): void
    {
        $this->post('/my/groups', $this->groupPayload())->assertRedirect('/login');
        $this->get('/my/groups')->assertRedirect('/login');
    }
}
