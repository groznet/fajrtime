<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.yookassa.ru/v3';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.yookassa.shop_id' => '123', 'services.yookassa.secret_key' => 'test_secret']);
        Http::preventStrayRequests();
    }

    private function remotePayment(string $id, string $status, string $amount = '500.00'): array
    {
        return [
            'id' => $id,
            'status' => $status,
            'amount' => ['value' => $amount, 'currency' => 'RUB'],
            'confirmation' => ['type' => 'redirect', 'confirmation_url' => 'https://yoomoney.ru/checkout/'.$id],
        ];
    }

    private function pendingPayment(User $user, string $gatewayId = 'yk-1', int $gross = 500_00): Payment
    {
        return $user->payments()->create([
            'gateway' => 'yookassa',
            'gateway_payment_id' => $gatewayId,
            'amount_gross' => $gross,
            'commission' => 25_00,
            'amount_net' => $gross - 25_00,
        ]);
    }

    public function test_wallet_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/wallet')->assertOk()->assertSee('Пополнить');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/wallet')->assertRedirect('/login');
    }

    public function test_top_up_creates_payment_and_redirects_to_yookassa(): void
    {
        Http::fake([self::API.'/payments' => Http::response($this->remotePayment('yk-new', 'pending'))]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/wallet/top-up', ['amount' => 500])
            ->assertRedirect('https://yoomoney.ru/checkout/yk-new');

        $payment = Payment::sole();
        $this->assertSame('yk-new', $payment->gateway_payment_id);
        $this->assertSame(500_00, $payment->amount_gross);
        $this->assertSame(25_00, $payment->commission);
        $this->assertSame(475_00, $payment->amount_net);
        $this->assertSame('pending', $payment->status);

        Http::assertSent(fn ($request) => $request['amount'] == ['value' => '500.00', 'currency' => 'RUB']
            && $request->hasHeader('Idempotence-Key', 'fajrrise-payment-'.$payment->id)
            && $request['confirmation']['return_url'] === route('wallet.payments.return', $payment));

        $this->assertSame(0, $user->fresh()->balance_available);
    }

    public function test_top_up_amount_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/wallet/top-up', ['amount' => 50])->assertSessionHasErrors('amount');
        $this->actingAs($user)->post('/wallet/top-up', ['amount' => 1_000_000])->assertSessionHasErrors('amount');
        $this->assertSame(0, Payment::count());
    }

    public function test_gateway_error_is_shown_to_user(): void
    {
        Http::fake([self::API.'/payments' => Http::response(['type' => 'error'], 401)]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/wallet/top-up', ['amount' => 500])->assertSessionHasErrors('amount');
        $this->assertSame('failed', Payment::sole()->status);
    }

    public function test_webhook_credits_balance_once(): void
    {
        Http::fake([self::API.'/payments/yk-1' => Http::response($this->remotePayment('yk-1', 'succeeded'))]);
        $user = User::factory()->create();
        $payment = $this->pendingPayment($user);

        $body = ['type' => 'notification', 'event' => 'payment.succeeded', 'object' => ['id' => 'yk-1']];
        $this->postJson('/webhooks/yookassa', $body)->assertOk();
        $this->postJson('/webhooks/yookassa', $body)->assertOk();

        $this->assertSame('succeeded', $payment->fresh()->status);
        $this->assertSame(475_00, $user->fresh()->balance_available);
        $this->assertSame(1, $user->transactions()->count());
        $this->assertSame(475_00, $user->transactions()->sole()->amount);
    }

    public function test_webhook_body_is_not_trusted(): void
    {
        // Notification claims success, but the API says the payment is still pending.
        Http::fake([self::API.'/payments/yk-1' => Http::response($this->remotePayment('yk-1', 'pending'))]);
        $user = User::factory()->create();
        $payment = $this->pendingPayment($user);

        $this->postJson('/webhooks/yookassa', ['event' => 'payment.succeeded', 'object' => ['id' => 'yk-1', 'status' => 'succeeded']])
            ->assertOk();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(0, $user->fresh()->balance_available);
    }

    public function test_amount_mismatch_is_not_credited(): void
    {
        Http::fake([self::API.'/payments/yk-1' => Http::response($this->remotePayment('yk-1', 'succeeded', '1.00'))]);
        $user = User::factory()->create();
        $payment = $this->pendingPayment($user);

        $this->postJson('/webhooks/yookassa', ['object' => ['id' => 'yk-1']])->assertOk();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(0, $user->fresh()->balance_available);
    }

    public function test_webhook_for_unknown_payment_is_ignored(): void
    {
        $this->postJson('/webhooks/yookassa', ['object' => ['id' => 'nope']])->assertOk();
        $this->postJson('/webhooks/yookassa', [])->assertOk();
    }

    public function test_canceled_payment_is_marked_failed(): void
    {
        Http::fake([self::API.'/payments/yk-1' => Http::response($this->remotePayment('yk-1', 'canceled'))]);
        $user = User::factory()->create();
        $payment = $this->pendingPayment($user);

        $this->postJson('/webhooks/yookassa', ['object' => ['id' => 'yk-1']])->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame(0, $user->fresh()->balance_available);
    }

    public function test_return_page_checks_status_and_credits(): void
    {
        Http::fake([self::API.'/payments/yk-1' => Http::response($this->remotePayment('yk-1', 'succeeded'))]);
        $user = User::factory()->create();
        $payment = $this->pendingPayment($user);

        $this->actingAs($user)
            ->get(route('wallet.payments.return', $payment))
            ->assertRedirect('/wallet')
            ->assertSessionHas('status', 'Баланс пополнен.');

        $this->assertSame(475_00, $user->fresh()->balance_available);
    }

    public function test_return_page_of_another_user_is_not_found(): void
    {
        $payment = $this->pendingPayment(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('wallet.payments.return', $payment))
            ->assertNotFound();
    }

    public function test_withdrawal_request_takes_amount_from_balance(): void
    {
        $user = User::factory()->create();
        $this->credit($user, 475_00);

        $this->actingAs($user)
            ->post('/wallet/withdrawals', ['amount' => 400, 'phone' => '8 (900) 123-45-67', 'bank' => 'Сбербанк'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/wallet');

        $withdrawal = $user->withdrawals()->sole();
        $this->assertSame(400_00, $withdrawal->amount);
        $this->assertSame('requested', $withdrawal->status);
        $this->assertEqualsCanonicalizing(['method' => 'sbp', 'phone' => '+79001234567', 'bank' => 'Сбербанк'], $withdrawal->details);
        $this->assertSame(75_00, $user->fresh()->balance_available);

        $this->actingAs($user)->get('/wallet')->assertOk()
            ->assertSee('Заявка на вывод')->assertSee('Ожидает')->assertSee("75\u{00A0}₽", false);
    }

    public function test_withdrawal_above_balance_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->credit($user, 100_00);

        $this->actingAs($user)
            ->post('/wallet/withdrawals', ['amount' => 200, 'phone' => '+79001234567', 'bank' => 'Т-Банк'])
            ->assertSessionHasErrorsIn('withdrawal', 'amount');

        $this->assertSame(0, $user->withdrawals()->count());
        $this->assertSame(100_00, $user->fresh()->balance_available);
    }

    public function test_only_one_open_withdrawal_request_at_a_time(): void
    {
        $user = User::factory()->create();
        $this->credit($user, 1000_00);
        $data = ['amount' => 100, 'phone' => '+79001234567', 'bank' => 'Т-Банк'];

        $this->actingAs($user)->post('/wallet/withdrawals', $data)->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/wallet/withdrawals', $data)->assertSessionHasErrorsIn('withdrawal', 'amount');

        $this->assertSame(900_00, $user->fresh()->balance_available);
    }

    public function test_commission_uses_setting_and_gross_for_net(): void
    {
        $commission = app(CommissionService::class);

        $this->assertSame(25_00, $commission->commissionFor(500_00));
        $this->assertSame(500_00, $commission->grossFor(475_00));
        $this->assertGreaterThanOrEqual(333_33, $commission->netFor($commission->grossFor(333_33)));

        Setting::create(['key' => 'commission_percent', 'value' => '10']);
        $this->assertSame(50_00, $commission->commissionFor(500_00));
    }

    public function test_money_formatting(): void
    {
        $this->assertSame("14\u{00A0}250\u{00A0}₽", Money::format(14_250_00));
        $this->assertSame("712,50\u{00A0}₽", Money::format(712_50));
        $this->assertSame('500.05', Money::toDecimal(500_05));
        $this->assertSame(500_05, Money::fromDecimal('500.05'));
        $this->assertSame(500_50, Money::fromDecimal('500.5'));
        $this->assertSame(500_00, Money::fromDecimal('500'));
    }

    private function credit(User $user, int $amount): void
    {
        $payment = $this->pendingPayment($user, 'seed-'.$user->id, $amount + 25_00);
        app(WalletService::class)->creditTopUp($payment);
    }
}
