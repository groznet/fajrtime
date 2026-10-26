<x-app-layout title="Панель">
    <x-slot name="header">Ас-саляму алейкум, {{ $user->name }}</x-slot>

    <x-card class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm text-gray-600">Баланс</p>
            <p class="text-2xl font-semibold"><x-money :amount="$user->balance_available" /></p>
        </div>
        <a href="{{ route('wallet') }}" class="px-4 py-2 rounded-md bg-emerald-700 text-white text-sm hover:bg-emerald-800">Пополнить</a>
    </x-card>

    <x-card title="Мои челленджи" class="mb-6">
        @if ($participations->isEmpty())
            <p class="text-gray-600">
                Вы пока не участвуете ни в одном челлендже.
                <a href="{{ route('groups.index') }}" class="underline text-emerald-800">Найти группу</a>
            </p>
        @else
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($participations as $participation)
                    @php($challenge = $participation->challenge)
                    <li class="py-2 flex flex-wrap justify-between gap-x-4">
                        <a href="{{ route('challenges.show', $challenge) }}" class="underline">{{ $challenge->group->name }}</a>
                        <span class="text-gray-600">
                            {{ $challenge->statusLabel() }} · {{ $challenge->start_date->translatedFormat('j F') }} — {{ $challenge->end_date->translatedFormat('j F') }}
                            · ставка <x-money :amount="$challenge->stake_amount" />
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    @if ($city === null)
        <x-card>
            <p>Укажите город в <a href="{{ route('settings.edit') }}" class="underline">настройках</a>, чтобы видеть время намаза.</p>
        </x-card>
    @else
        <div class="grid gap-6 md:grid-cols-2">
            <x-card title="Следующий Фаджр">
                @if ($nextFajr)
                    <p class="text-3xl font-semibold tabular-nums">{{ $nextFajr->format('H:i') }}</p>
                    <p class="mt-1 text-gray-600">{{ $nextFajr->translatedFormat('l, j F') }}, {{ $city->name }}</p>
                @else
                    <p class="text-gray-600">Время Фаджра для вашего города сейчас не рассчитывается.</p>
                @endif
            </x-card>

            <x-card title="Время намаза сегодня">
                <x-prayer-times-table :times="$times" :timezone="$user->timezone" />
            </x-card>
        </div>
    @endif
</x-app-layout>
