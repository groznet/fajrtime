<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\YooKassaClient;
use App\Support\Money;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    public function __construct(
        private YooKassaClient $yookassa,
        private CommissionService $commission,
        private WalletService $wallet,
    ) {}

    /**
     * Create a pending top-up and return the URL where the user pays.
     */
    public function startTopUp(User $user, int $gross): Payment
    {
        $payment = $user->payments()->create([
            'gateway' => 'yookassa',
            'amount_gross' => $gross,
            'commission' => $this->commission->commissionFor($gross),
            'amount_net' => $this->commission->netFor($gross),
        ]);

        try {
            $remote = $this->yookassa->createPayment(
                $payment,
                route('wallet.payments.return', $payment),
                $user->email,
            );
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed']);
            throw $e;
        }

        $payment->update([
            'gateway_payment_id' => $remote['id'],
            'confirmation_url' => $remote['confirmation']['confirmation_url'] ?? null,
        ]);

        return $payment;
    }

    /**
     * Fetch the payment's real status from ЮKassa and apply it. Used by both the webhook
     * and the return page: ЮKassa webhooks are not signed, so we never trust their body.
     */
    public function sync(Payment $payment): Payment
    {
        if (! $payment->isPending() || $payment->gateway_payment_id === null) {
            return $payment;
        }

        $remote = $this->yookassa->getPayment($payment->gateway_payment_id);

        match ($remote['status'] ?? null) {
            'succeeded' => $this->succeed($payment, $remote),
            'canceled' => $payment->update(['status' => 'failed']),
            default => null, // pending / waiting_for_capture: nothing to do yet
        };

        return $payment->refresh();
    }

    private function succeed(Payment $payment, array $remote): void
    {
        $paid = Money::fromDecimal((string) ($remote['amount']['value'] ?? '0'));

        if (($remote['amount']['currency'] ?? null) !== 'RUB' || $paid !== $payment->amount_gross) {
            Log::error('ЮKassa amount mismatch', ['payment_id' => $payment->id, 'remote' => $remote['amount'] ?? null]);

            return;
        }

        $this->wallet->creditTopUp($payment);
    }
}
