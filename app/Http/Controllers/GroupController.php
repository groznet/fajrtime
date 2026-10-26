<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupRequest;
use App\Models\Group;
use App\Models\Setting;
use App\Services\CommissionService;
use App\Services\GroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'cycle_days' => ['nullable', 'integer', 'in:'.implode(',', config('fajr.cycle_days'))],
            'stake_from' => ['nullable', 'integer', 'min:0'],
            'stake_to' => ['nullable', 'integer', 'min:0'],
        ]);

        $groups = Group::query()
            ->where('visibility', 'public')
            ->when($filters['cycle_days'] ?? null, fn ($q, $days) => $q->where('cycle_days', $days))
            ->when($filters['stake_from'] ?? null, fn ($q, $rub) => $q->where('stake_amount', '>=', $rub * 100))
            ->when($filters['stake_to'] ?? null, fn ($q, $rub) => $q->where('stake_amount', '<=', $rub * 100))
            ->withCount('members')
            ->with('openChallenge')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('groups.index', ['groups' => $groups, 'filters' => $filters]);
    }

    public function show(Request $request, Group $group): View
    {
        abort_unless($group->isViewableBy($request->user()), 404);

        return $this->render($request, $group, null);
    }

    public function invite(Request $request, string $token): View
    {
        $group = Group::where('invite_token', $token)->firstOrFail();

        return $this->render($request, $group, $token);
    }

    public function mine(Request $request): View
    {
        $groups = $request->user()->groups()
            ->withCount('members')
            ->with('openChallenge')
            ->latest('group_members.joined_at')
            ->get();

        return view('groups.mine', ['groups' => $groups]);
    }

    public function create(): View
    {
        return view('groups.create', [
            'stakeMin' => Setting::int('stake_min') / 100,
            'stakeMax' => Setting::int('stake_max') / 100,
            'cycleDays' => config('fajr.cycle_days'),
        ]);
    }

    public function store(StoreGroupRequest $request, GroupService $groups): RedirectResponse
    {
        $group = $groups->create($request->user(), $request->groupData());

        return redirect()->route('groups.show', $group)->with('status', 'Группа создана. Теперь запустите в ней челлендж.');
    }

    public function join(Request $request, Group $group, GroupService $groups): RedirectResponse
    {
        $token = $request->input('invite');
        abort_unless($group->isViewableBy($request->user(), is_string($token) ? $token : null), 404);

        $groups->join($request->user(), $group);

        return redirect()->route('groups.show', $group)->with('status', 'Вы вступили в группу.');
    }

    public function leave(Request $request, Group $group, GroupService $groups): RedirectResponse
    {
        $groups->leave($request->user(), $group);

        return redirect()->route('groups.mine')->with('status', 'Вы вышли из группы.');
    }

    private function render(Request $request, Group $group, ?string $inviteToken): View
    {
        $user = $request->user();
        $challenge = $group->openChallenge()->first();

        return view('groups.show', [
            'group' => $group->loadCount('members')->load('creator'),
            'user' => $user,
            'isMember' => $group->hasMember($user),
            'isCreator' => $user?->id === $group->creator_id,
            'inviteToken' => $inviteToken,
            'challenge' => $challenge,
            'participantsCount' => $challenge?->participants()->where('status', 'active')->count(),
            'participating' => $challenge && $user
                ? $challenge->participants()->where('user_id', $user->id)->where('status', 'active')->exists()
                : false,
            'pastChallenges' => $group->challenges()->whereNotIn('status', \App\Models\Challenge::OPEN)->latest('id')->limit(10)->get(),
            'topUpNeeded' => $challenge && $user && $user->balance_available < $challenge->stake_amount
                ? app(CommissionService::class)->grossFor($challenge->stake_amount - $user->balance_available)
                : null,
            'minParticipants' => Setting::int('min_participants'),
            'maxParticipants' => Setting::int('max_participants'),
        ]);
    }
}
