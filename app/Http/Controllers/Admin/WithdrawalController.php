<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WithdrawalController extends Controller
{
    public function index(): View
    {
        return view('admin.withdrawals', [
            // Requests waiting for a decision first, then the most recent ones.
            'withdrawals' => Withdrawal::with('user:id,name,email')
                ->orderByRaw("status = 'requested' desc")
                ->latest('id')
                ->paginate(30),
        ]);
    }

    public function pay(Request $request, Withdrawal $withdrawal, WalletService $wallet): RedirectResponse
    {
        DB::transaction(function () use ($request, $withdrawal, $wallet) {
            $wallet->markWithdrawalPaid($withdrawal);
            AdminAction::record($request->user(), 'withdrawal.paid', $withdrawal);
        });

        return back()->with('status', 'Заявка отмечена как выплаченная.');
    }

    public function reject(Request $request, Withdrawal $withdrawal, WalletService $wallet): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [], ['reason' => 'причина']);

        DB::transaction(function () use ($request, $withdrawal, $wallet, $data) {
            $wallet->rejectWithdrawal($withdrawal);
            AdminAction::record($request->user(), 'withdrawal.rejected', $withdrawal, $data['reason']);
        });

        return back()->with('status', 'Заявка отклонена, деньги возвращены на баланс.');
    }
}
