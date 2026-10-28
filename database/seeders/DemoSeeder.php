<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\CheckIn;
use App\Models\City;
use App\Models\Group;
use App\Models\Payment;
use App\Models\User;
use App\Services\ChallengeService;
use App\Services\CommissionService;
use App\Services\GroupService;
use App\Services\SettlementService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo data for learning the app: members, groups, a finished, a running and a recruiting challenge,
 * payments, a withdrawal and notifications. NOT part of DatabaseSeeder; run it by hand:
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Every balance change goes through the real services, so the ledger stays consistent.
 * All demo accounts use the password "password".
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private WalletService $wallet;

    private ChallengeService $challenges;

    private CommissionService $commission;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('DemoSeeder refuses to run in production (it creates fake users).');

            return;
        }

        if (User::where('email', 'demo@fajrrise.test')->exists()) {
            $this->command->warn('Demo data already exists. Run "php artisan migrate:fresh --seed" and then this seeder again to reset.');

            return;
        }

        $this->call(CitySeeder::class);

        $this->wallet = app(WalletService::class);
        $this->challenges = app(ChallengeService::class);
        $this->commission = app(CommissionService::class);
        $groups = app(GroupService::class);
        $today = CarbonImmutable::parse($this->challenges->today());

        $cities = City::pluck('id', 'name');

        $admin = $this->user('Администратор', 'admin@fajrrise.test', $cities['Грозный']);
        $admin->forceFill(['role' => 'admin'])->save();

        $demo = $this->user('Демо Участник', 'demo@fajrrise.test', $cities['Грозный']);
        $newbie = $this->user('Новичок', 'newbie@fajrrise.test', $cities['Грозный']);

        // Past challenge members (m1-m6), running challenge members (m7-m12), private group (m13-m14).
        $names = [
            ['Ахмад Исаев', 'Грозный'], ['Муса Алиев', 'Грозный'], ['Ибрагим Хасанов', 'Назрань'],
            ['Юсуф Магомедов', 'Махачкала'], ['Зайнаб Умарова', 'Грозный'], ['Али Гаджиев', 'Махачкала'],
            ['Хамзат Беков', 'Грозный'], ['Саид Далгатов', 'Нальчик'], ['Умар Тагиров', 'Владикавказ'],
            ['Рамазан Кадыров', 'Грозный'], ['Билал Эльдаров', 'Черкесск'], ['Идрис Мусаев', 'Астрахань'],
            ['Хусейн Абдулаев', 'Грозный'], ['Мариам Сулейманова', 'Махачкала'],
        ];
        $m = [];
        foreach ($names as $i => [$name, $city]) {
            $m[$i + 1] = $this->user($name, 'member'.($i + 1).'@fajrrise.test', $cities[$city]);
        }

        // --- Groups -------------------------------------------------------------------------------------------
        $grozny = $groups->create($m[1], [
            'name' => 'Ранний подъём — Грозный',
            'description' => 'Дружная группа для тех, кто хочет не пропускать Фаджр. Ставка небольшая, цель — постоянство.',
            'stake_amount' => 500_00, 'cycle_days' => 7, 'visibility' => 'public',
        ]);
        $students = $groups->create($m[7], [
            'name' => 'Студенты на Фаджр',
            'description' => 'Для студентов и молодых специалистов. 10 дней, чтобы выработать привычку.',
            'stake_amount' => 300_00, 'cycle_days' => 10, 'visibility' => 'public',
        ]);
        $business = $groups->create($m[13], [
            'name' => 'Предприниматели: утро с Фаджра',
            'description' => 'Закрытая группа, вход по ссылке-приглашению.',
            'stake_amount' => 1_000_00, 'cycle_days' => 14, 'visibility' => 'private',
        ]);
        $own = $groups->create($newbie, [
            'name' => 'Моя первая группа',
            'description' => 'Группа Новичка: создайте здесь челлендж, чтобы попробовать роль создателя.',
            'stake_amount' => 200_00, 'cycle_days' => 7, 'visibility' => 'public',
        ]);

        foreach ([1, 2, 3, 4, 5, 6] as $i) {
            $groups->join($m[$i], $grozny);
        }
        $groups->join($demo, $students);
        $groups->join($newbie, $grozny);
        foreach ([8, 9, 10, 11, 12] as $i) {
            $groups->join($m[$i], $students);
        }
        $groups->join($m[14], $business);

        // --- Money: everyone tops up enough for their stake (+ a bit) -----------------------------------------
        foreach (array_merge([$demo, $newbie], $m) as $user) {
            $this->topUp($user, 1_200_00, 'demo-'.Str::uuid());
        }
        $this->topUp($demo, 600_00, 'demo-'.Str::uuid());

        // A pending and a failed payment, so the wallet history shows every status.
        $this->payment($newbie, 500_00, 'pending');
        $this->payment($newbie, 300_00, 'failed');

        // --- 1. Finished challenge in the Grozny group (m1-m6): m3 and m6 were eliminated -----------------------
        $past = $this->challengeRow($grozny, $today->subDays(12), 'active');
        $pastPlan = [1 => null, 2 => null, 3 => 3, 4 => null, 5 => null, 6 => 5]; // member => day of the miss
        foreach ($pastPlan as $i => $missDay) {
            $this->participate($m[$i], $past, $missDay);
        }
        $this->settle($past);

        // Withdrawals need no open challenge, so do them now: one waiting for the admin, one already paid.
        $this->wallet->requestWithdrawal($m[4], 300_00, ['method' => 'sbp', 'phone' => '+79281234567', 'bank' => 'Сбербанк']);
        $paid = $this->wallet->requestWithdrawal($m[5], 400_00, ['method' => 'sbp', 'phone' => '+79287654321', 'bank' => 'Тинькофф']);
        $this->wallet->markWithdrawalPaid($paid);

        // --- 2. Running challenge in the Students group (day 3 of 10): m8..m12 + demo; m12 was eliminated yesterday
        $running = $this->challengeRow($students, $today->subDays(2), 'active');
        $this->participate($demo, $running, null);
        foreach ([8, 9, 10, 11] as $i) {
            $this->participate($m[$i], $running, null);
        }
        $this->participate($m[12], $running, 2);

        // --- 3. Recruiting challenge in the Grozny group: three members already joined; Новичок can join -------
        $recruiting = $this->challenges->create($grozny->refresh(), $today->addDays(3));
        foreach ([1, 2, 3] as $i) {
            $this->challenges->join($m[$i], $recruiting);
        }

        $this->notifications($m[12], $running, $today->subDay());
        $this->notifications($m[6], $past, $today->subDays(8));
        $this->payoutNotice($m[1], $past);

        // Today's/tomorrow's codes for the running challenge (only appears if Fajr is within the schedule window).
        app(\App\Services\CheckInService::class)->scheduleDue();

        $this->command->info('Demo data created. All passwords: '.self::PASSWORD);
        $this->command->table(['Role', 'Email', 'What to try'], [
            ['Admin', 'admin@fajrrise.test', '/admin: users, groups, challenges, payments, withdrawals'],
            ['Participant', 'demo@fajrrise.test', 'In a running challenge: dashboard, check-in, wallet, notifications'],
            ['Newbie', 'newbie@fajrrise.test', 'Free balance: join the recruiting challenge, create a challenge in own group'],
            ['Members', 'member1..14@fajrrise.test', 'Past/running/recruiting participants'],
        ]);
        $this->command->line('Private group invite link: /invite/'.$business->invite_token);
    }

    private function user(string $name, string $email, int $cityId): User
    {
        $city = City::find($cityId);

        $user = new User(['name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'city_id' => $cityId, 'timezone' => $city->timezone]);
        $user->forceFill(['email_verified_at' => now(), 'terms_accepted_at' => now(), 'personal_data_consent_at' => now()])->save();

        return $user;
    }

    /** A succeeded top-up that credits (at least) $net to the balance, via the real wallet service. */
    private function topUp(User $user, int $net, string $gatewayId): void
    {
        $gross = $this->commission->grossFor($net);
        $payment = $this->payment($user, $gross, 'pending', $gatewayId);
        $this->wallet->creditTopUp($payment);
    }

    private function payment(User $user, int $gross, string $status, ?string $gatewayId = null): Payment
    {
        $commission = $this->commission->commissionFor($gross);

        return Payment::create([
            'user_id' => $user->id,
            'gateway' => 'yookassa',
            'gateway_payment_id' => $gatewayId ?? 'demo-'.Str::uuid(),
            'amount_gross' => $gross,
            'commission' => $commission,
            'amount_net' => $gross - $commission,
            'status' => $status,
        ]);
    }

    /** A challenge row in the past/present; the stake and length are frozen from the group like the real service does. */
    private function challengeRow(Group $group, CarbonImmutable $start, string $status): Challenge
    {
        return $group->challenges()->create([
            'stake_amount' => $group->stake_amount,
            'start_date' => $start,
            'end_date' => $start->addDays($group->cycle_days - 1),
            'status' => $status,
        ]);
    }

    /**
     * Lock the stake and add check-in history up to yesterday (or up to the end for a finished challenge).
     * $missDay is the 1-based day of the missed check-in, or null for someone who never missed.
     */
    private function participate(User $user, Challenge $challenge, ?int $missDay): void
    {
        $this->wallet->lockStake($user, $challenge);

        $participant = $challenge->participants()->create(['user_id' => $user->id, 'status' => 'active']);
        $today = CarbonImmutable::parse($this->challenges->today());
        $start = CarbonImmutable::parse($challenge->start_date->toDateString());
        $last = $challenge->end_date->lt($today) ? CarbonImmutable::parse($challenge->end_date->toDateString()) : $today->subDay();

        for ($day = 1; $start->addDays($day - 1)->lte($last); $day++) {
            $date = $start->addDays($day - 1);
            $missed = $missDay === $day;

            $this->checkIn($user, $date, $missed);

            if ($missed) {
                $participant->update(['status' => 'eliminated', 'eliminated_on' => $date->toDateString()]);
                break;
            }
        }
    }

    private function checkIn(User $user, CarbonImmutable $date, bool $missed): void
    {
        $fajr = CarbonImmutable::parse($date->toDateString().' 05:00', $user->timezone)->utc()->addMinutes(random_int(-20, 20));
        $reveal = $fajr->addSeconds(random_int(0, 599));

        CheckIn::create([
            'user_id' => $user->id,
            'date' => $date->toDateString(),
            'fajr_at' => $fajr,
            'code' => str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT),
            'reveal_at' => $reveal,
            'expires_at' => $reveal->addMinutes(5),
            'confirmed_at' => $missed ? null : $reveal->addSeconds(random_int(10, 120)),
            'attempts' => $missed ? 0 : random_int(0, 1),
            'status' => $missed ? 'missed' : 'confirmed',
        ]);
    }

    private function settle(Challenge $challenge): void
    {
        app(SettlementService::class)->settle($challenge->id);
    }

    /** On-site notification for an eliminated participant, same shape as ParticipantEliminated::toArray(). */
    private function notifications(User $user, Challenge $challenge, CarbonImmutable $when): void
    {
        $this->insertNotification($user, 'App\Notifications\ParticipantEliminated', [
            'title' => 'Вы выбыли из челленджа',
            'body' => 'Не подтверждён Фаджр '.$when->translatedFormat('j F').'. Ставка группы «'.$challenge->group->name.'» потеряна.',
            'url' => route('challenges.show', $challenge, absolute: false),
        ], $when);
    }

    private function payoutNotice(User $user, Challenge $challenge): void
    {
        $challenge->refresh();
        $payout = $challenge->participants()->where('user_id', $user->id)->value('payout_amount');

        $this->insertNotification($user, 'App\Notifications\ChallengeCompleted', [
            'title' => 'Челлендж завершён',
            'body' => 'Вы дошли до конца в группе «'.$challenge->group->name.'». Выплата: '.number_format($payout / 100, 2, ',', ' ').' ₽.',
            'url' => route('challenges.show', $challenge, absolute: false),
        ], CarbonImmutable::now()->subDays(5));
    }

    private function insertNotification(User $user, string $type, array $data, CarbonImmutable $when): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'read_at' => null,
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    }
}
