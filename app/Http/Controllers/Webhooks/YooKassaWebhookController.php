<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ЮKassa HTTP notifications (payment.succeeded, payment.canceled).
 * The body is only used to find the payment; its status is re-fetched from the API.
 */
class YooKassaWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGatewayService $gateway): Response
    {
        $gatewayId = $request->input('object.id');

        $payment = is_string($gatewayId)
            ? Payment::where('gateway', 'yookassa')->where('gateway_payment_id', $gatewayId)->first()
            : null;

        // Unknown payment: answer 200 so ЮKassa stops retrying.
        if ($payment !== null) {
            // An exception here returns 500 and ЮKassa retries later, which is what we want.
            $gateway->sync($payment);
        }

        return response()->noContent(200);
    }
}
