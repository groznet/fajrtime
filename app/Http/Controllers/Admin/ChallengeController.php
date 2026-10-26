<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChallengeController extends Controller
{
    public function show(Challenge $challenge): View
    {
        return view('admin.challenge', [
            'challenge' => $challenge->load('group'),
            'participants' => $challenge->participants()->with('user:id,name,email')->orderBy('id')->get(),
            'actions' => AdminAction::with('admin:id,name')
                ->where('subject_type', 'ChallengeParticipant')
                ->whereIn('subject_id', $challenge->participants()->pluck('id'))
                ->latest('id')->get(),
        ]);
    }

    /**
     * Undo a wrongly applied elimination (site downtime, wrong prayer time). The stake is still locked until
     * settlement, so no money moves: the participant simply plays on. Only possible while the challenge runs.
     */
    public function restore(Request $request, ChallengeParticipant $participant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], [], ['reason' => 'причина']);

        DB::transaction(function () use ($request, $participant, $data) {
            $participant = ChallengeParticipant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            $challenge = Challenge::whereKey($participant->challenge_id)->lockForUpdate()->firstOrFail();

            if ($participant->status !== 'eliminated') {
                throw ValidationException::withMessages(['participant' => 'Этот участник не исключён.']);
            }

            if ($challenge->status !== 'active') {
                throw ValidationException::withMessages(['participant' => 'Вернуть участника можно только пока челлендж идёт: после подведения итогов ставка уже распределена.']);
            }

            if ($participant->user->hasOpenChallenges()) {
                throw ValidationException::withMessages(['participant' => 'Участник уже вступил в другой челлендж.']);
            }

            $participant->update(['status' => 'active', 'eliminated_on' => null]);
            AdminAction::record($request->user(), 'participant.restored', $participant, $data['reason']);
        });

        return back()->with('status', 'Участник возвращён в челлендж.');
    }
}
