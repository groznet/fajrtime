<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChallengeRequest;
use App\Models\Challenge;
use App\Models\Group;
use App\Models\Setting;
use App\Services\ChallengeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChallengeController extends Controller
{
    public function store(StoreChallengeRequest $request, Group $group, ChallengeService $challenges): RedirectResponse
    {
        abort_unless($request->user()->id === $group->creator_id, 403);

        $challenge = $challenges->create($group, CarbonImmutable::parse($request->validated('start_date')));

        return redirect()->route('challenges.show', $challenge)->with('status', 'Челлендж создан. Участники могут вступать до дня старта.');
    }

    public function show(Request $request, Challenge $challenge): View
    {
        $group = $challenge->group;
        abort_unless($group->isViewableBy($request->user()), 404);

        return view('challenges.show', [
            'challenge' => $challenge,
            'group' => $group,
            'participants' => $challenge->participants()->with('user:id,name')->orderBy('id')->get(),
            'participating' => $challenge->participants()->where('user_id', $request->user()->id)->where('status', 'active')->exists(),
            'minParticipants' => Setting::int('min_participants'),
        ]);
    }

    public function join(Request $request, Challenge $challenge, ChallengeService $challenges): RedirectResponse
    {
        $challenges->join($request->user(), $challenge);

        return redirect()->route('challenges.show', $challenge)->with('status', 'Вы в челлендже. Ставка заблокирована на балансе.');
    }

    public function leave(Request $request, Challenge $challenge, ChallengeService $challenges): RedirectResponse
    {
        $challenges->leave($request->user(), $challenge);

        return redirect()->route('challenges.show', $challenge)->with('status', 'Вы вышли из челленджа. Ставка возвращена на баланс.');
    }
}
