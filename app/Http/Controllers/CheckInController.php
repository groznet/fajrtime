<?php

namespace App\Http\Controllers;

use App\Enums\CheckInResult;
use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckInController extends Controller
{
    public function show(Request $request, CheckInService $checkIns): View
    {
        $user = $request->user();

        return view('check-in.show', [
            'state' => $checkIns->state($user),
            'hasRunningChallenge' => $user->hasRunningChallenge(),
        ]);
    }

    /**
     * Polled by the page every ~15 seconds.
     */
    public function state(Request $request, CheckInService $checkIns): JsonResponse
    {
        return response()->json($checkIns->state($request->user()))
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, CheckInService $checkIns): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);

        $result = $checkIns->submit($request->user(), $data['code']);

        return response()->json([
            'result' => $result->value,
            'message' => $result->message(),
            'state' => $checkIns->state($request->user()),
        ], in_array($result, [CheckInResult::Confirmed, CheckInResult::Already]) ? 200 : 422)
            ->header('Cache-Control', 'no-store');
    }
}
