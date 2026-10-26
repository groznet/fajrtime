<x-app-layout title="Кошелёк">
    <x-slot name="header">Кошелёк</x-slot>

    <div class="space-y-6">
        <div class="grid gap-6 md:grid-cols-2">
            <x-card title="Баланс">
                <p class="text-3xl font-semibold"><x-money :amount="$user->balance_available" /></p>
                <p class="mt-1 text-gray-600">доступно для участия в челленджах</p>
                @if ($user->balance_locked > 0)
                    <p class="mt-3 text-sm text-gray-600">
                        Заблокировано в челленджах: <x-money :amount="$user->balance_locked" />
                    </p>
                @endif
            </x-card>

            <x-card title="Пополнить">
                <form method="post" action="{{ route('wallet.top-up') }}" class="space-y-4"
                    x-data="{ amount: {{ $errors->withdrawal->any() ? 500 : (int) old('amount', 500) }}, percent: {{ $commissionPercent }} }">
                    @csrf

                    <div>
                        <x-input-label for="amount" value="Сумма, ₽" />
                        <x-text-input id="amount" name="amount" type="number" class="mt-1 block w-full"
                            x-model.number="amount" min="{{ $topupMin }}" max="{{ $topupMax }}" step="1" required />
                        <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                    </div>

                    <p class="text-sm text-gray-600" x-show="amount > 0">
                        Комиссия {{ rtrim(rtrim(number_format($commissionPercent, 2, ',', ''), '0'), ',') }}%:
                        <span class="tabular-nums" x-text="(Math.round(amount * percent) / 100).toLocaleString('ru-RU') + ' ₽'"></span>.
                        На баланс поступит
                        <span class="font-medium tabular-nums" x-text="(Math.round(amount * (100 - percent)) / 100).toLocaleString('ru-RU') + ' ₽'"></span>.
                    </p>

                    <x-primary-button>Перейти к оплате</x-primary-button>
                    <p class="text-xs text-gray-500">Оплата картой или через СБП на странице ЮKassa.</p>
                </form>
            </x-card>
        </div>

        <x-card title="История операций">
            @if ($transactions->isEmpty())
                <p class="text-gray-600">Операций пока нет.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pr-4 font-normal">Дата</th>
                                <th class="py-2 pr-4 font-normal">Операция</th>
                                <th class="py-2 text-right font-normal">Сумма</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($transactions as $transaction)
                                <tr>
                                    <td class="py-2 pr-4 whitespace-nowrap text-gray-600">
                                        {{ $transaction->created_at->setTimezone($user->timezone)->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="py-2 pr-4">{{ $transaction->label() }}</td>
                                    <td class="py-2 text-right {{ $transaction->amount >= 0 ? 'text-emerald-700' : 'text-gray-900' }}">
                                        {{ $transaction->amount > 0 ? '+' : '' }}<x-money :amount="$transaction->amount" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $transactions->links() }}</div>
            @endif
        </x-card>

        <x-card title="Вывод средств">
            <p class="text-sm text-gray-600 mb-4">
                Выплата через СБП на ваш номер телефона. Заявки обрабатываются вручную в течение 3 рабочих дней.
                Вывод недоступен, пока вы участвуете в активном челлендже.
            </p>

            <form method="post" action="{{ route('wallet.withdraw') }}" class="grid gap-4 sm:grid-cols-3 sm:items-start">
                @csrf

                <div>
                    <x-input-label for="withdraw_amount" value="Сумма, ₽" />
                    <x-text-input id="withdraw_amount" name="amount" type="number" class="mt-1 block w-full"
                        :value="$errors->withdrawal->any() ? old('amount') : ''" min="{{ $withdrawalMin }}" step="1" required />
                    <x-input-error class="mt-2" :messages="$errors->withdrawal->get('amount')" />
                </div>

                <div>
                    <x-input-label for="phone" value="Телефон (СБП)" />
                    <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone')"
                        placeholder="+7 900 123-45-67" autocomplete="tel" required />
                    <x-input-error class="mt-2" :messages="$errors->withdrawal->get('phone')" />
                </div>

                <div>
                    <x-input-label for="bank" value="Банк" />
                    <x-text-input id="bank" name="bank" type="text" class="mt-1 block w-full" :value="old('bank')"
                        placeholder="Сбербанк" maxlength="100" required />
                    <x-input-error class="mt-2" :messages="$errors->withdrawal->get('bank')" />
                </div>

                <div class="sm:col-span-3">
                    <x-primary-button>Запросить вывод</x-primary-button>
                </div>
            </form>

            @if ($withdrawals->isNotEmpty())
                <h3 class="mt-6 mb-2 font-medium">Ваши заявки</h3>
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($withdrawals as $withdrawal)
                        <li class="py-2 flex justify-between gap-4">
                            <span class="text-gray-600">{{ $withdrawal->created_at->setTimezone($user->timezone)->format('d.m.Y') }}</span>
                            <x-money :amount="$withdrawal->amount" />
                            <span>{{ $withdrawal->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
