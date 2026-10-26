<?php

namespace App\Http\Controllers;

use App\Http\Requests\TopUpRequest;
use App\Http\Requests\WithdrawalRequest;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\CommissionService;
use App\Services\PaymentGatewayService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(Request $request, CommissionService $commission): View
    {
        $user = $request->user();

        return view('wallet.index', [
            'user' => $user,
            'commissionPercent' => $commission->percent(),
            'topupMin' => Setting::int('topup_min') / 100,
            'topupMax' => Setting::int('topup_max') / 100,
            'withdrawalMin' => Setting::int('withdrawal_min') / 100,
            'transactions' => $user->transactions()->latest('id')->paginate(20),
            'withdrawals' => $user->withdrawals()->latest('id')->limit(5)->get(),
        ]);
    }

    public function topUp(TopUpRequest $request, PaymentGatewayService $gateway): RedirectResponse
    {
        try {
            $payment = $gateway->startTopUp($request->user(), $request->amountKopecks());
        } catch (\Throwable $e) {
            Log::error('ЮKassa payment creation failed', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);

            return back()->withInput()->withErrors(['amount' => 'Не удалось создать платёж. Попробуйте ещё раз через минуту.']);
        }

        return redirect()->away($payment->confirmation_url);
    }

    /**
     * The user comes back here from the ЮKassa payment page. The webhook may not have
     * arrived yet (or cannot reach a local machine at all), so check the status ourselves.
     */
    public function paymentReturn(Request $request, Payment $payment, PaymentGatewayService $gateway): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 404);

        try {
            $payment = $gateway->sync($payment);
        } catch (\Throwable $e) {
            Log::warning('ЮKassa status check failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
        }

        $message = match ($payment->status) {
            'succeeded' => 'Баланс пополнен.',
            'failed' => 'Платёж не прошёл. Деньги не списаны.',
            default => 'Платёж обрабатывается. Баланс обновится, как только банк его подтвердит.',
        };

        return redirect()->route('wallet')->with('status', $message);
    }

    public function withdraw(WithdrawalRequest $request, WalletService $wallet): RedirectResponse
    {
        try {
            $wallet->requestWithdrawal($request->user(), $request->amountKopecks(), $request->details());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors(), 'withdrawal');
        }

        return redirect()->route('wallet')->with('status', 'Заявка на вывод создана. Мы обработаем её в течение 3 рабочих дней.');
    }
}
