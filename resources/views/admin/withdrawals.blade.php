<x-app-layout title="Заявки на вывод">
    <x-slot name="header"><a href="{{ route('admin.index') }}" class="hover:underline">Админка</a> · заявки на вывод</x-slot>

    <x-card>
        @if ($withdrawals->isEmpty())
            <p class="text-gray-600">Заявок пока нет.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($withdrawals as $withdrawal)
                    <li class="py-4 text-sm">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p>
                                <span class="font-medium">{{ $withdrawal->user->name }}</span>
                                <span class="text-gray-500">{{ $withdrawal->user->email }}</span>
                            </p>
                            <p class="text-lg font-semibold"><x-money :amount="$withdrawal->amount" /></p>
                        </div>
                        <p class="mt-1 text-gray-600">
                            @foreach (($withdrawal->details ?? []) as $key => $value)
                                {{ $key === 'phone' ? 'Телефон' : ($key === 'bank' ? 'Банк' : $key) }}: {{ $value }}@unless ($loop->last) · @endunless
                            @endforeach
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $withdrawal->created_at->setTimezone(auth()->user()->timezone)->format('d.m.Y H:i') }} · {{ $withdrawal->label() }}
                        </p>

                        @if ($withdrawal->status === 'requested')
                            <div class="mt-3 flex flex-wrap items-start gap-3">
                                <form method="post" action="{{ route('admin.withdrawals.pay', $withdrawal) }}">
                                    @csrf
                                    <x-primary-button>Выплачено</x-primary-button>
                                </form>
                                <form method="post" action="{{ route('admin.withdrawals.reject', $withdrawal) }}" class="flex flex-wrap items-start gap-2">
                                    @csrf
                                    <div>
                                        <x-text-input name="reason" placeholder="Причина отказа" class="w-64 text-sm" required minlength="3" maxlength="500" />
                                    </div>
                                    <button class="px-3 py-2 rounded-md border border-red-300 text-red-700 text-sm hover:bg-red-50">Отклонить и вернуть деньги</button>
                                </form>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $withdrawals->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
