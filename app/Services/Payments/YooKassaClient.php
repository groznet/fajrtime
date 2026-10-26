<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the ЮKassa API v3: https://yookassa.ru/developers/api
 */
class YooKassaClient
{
    /**
     * Create a payment and return the ЮKassa payment object.
     * Idempotence-Key is our payment id, so a retried request never charges twice.
     */
    public function createPayment(Payment $payment, string $returnUrl, string $customerEmail): array
    {
        $amount = ['value' => Money::toDecimal($payment->amount_gross), 'currency' => 'RUB'];
        $description = "Пополнение баланса FajrRise #{$payment->id}";

        $body = [
            'amount' => $amount,
            'capture' => true,
            'confirmation' => ['type' => 'redirect', 'return_url' => $returnUrl],
            'description' => $description,
            'metadata' => ['payment_id' => $payment->id],
        ];

        if (config('services.yookassa.send_receipt')) {
            $body['receipt'] = [
                'customer' => ['email' => $customerEmail],
                'items' => [[
                    'description' => $description,
                    'quantity' => '1.00',
                    'amount' => $amount,
                    'vat_code' => config('services.yookassa.vat_code'),
                    'payment_mode' => 'full_payment',
                    'payment_subject' => 'service',
                ]],
            ];
        }

        return $this->http()
            ->withHeader('Idempotence-Key', 'fajrrise-payment-'.$payment->id)
            ->post('/payments', $body)
            ->throw()
            ->json();
    }

    public function getPayment(string $gatewayPaymentId): array
    {
        return $this->http()
            ->get('/payments/'.rawurlencode($gatewayPaymentId))
            ->throw()
            ->json();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.yookassa.base_url'))
            ->withBasicAuth((string) config('services.yookassa.shop_id'), (string) config('services.yookassa.secret_key'))
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 500, fn (\Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()), throw: false);
    }
}
