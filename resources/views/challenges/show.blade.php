<x-app-layout title="Челлендж">
    <x-slot name="header">
        <a href="{{ route('groups.show', $group) }}" class="hover:underline">{{ $group->name }}</a> · челлендж
    </x-slot>

    <div class="space-y-6">
        <x-card>
            <p class="text-lg font-medium">{{ $challenge->statusLabel() }}</p>
            <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-4">
                <div><dt class="text-gray-500">Старт</dt><dd class="font-medium">{{ $challenge->start_date->translatedFormat('j F Y') }}</dd></div>
                <div><dt class="text-gray-500">Финиш</dt><dd class="font-medium">{{ $challenge->end_date->translatedFormat('j F Y') }}</dd></div>
                <div><dt class="text-gray-500">Ставка</dt><dd class="font-medium"><x-money :amount="$challenge->stake_amount" /></dd></div>
                <div><dt class="text-gray-500">Участников</dt><dd class="font-medium">{{ $participants->where('status', '!=', 'left')->count() }}</dd></div>
            </dl>

            @if ($challenge->isRecruiting())
                <p class="mt-4 text-sm text-gray-600">
                    Набор идёт до 00:00 по Москве {{ $challenge->start_date->translatedFormat('j F') }}.
                    Для старта нужно минимум {{ $minParticipants }} участников, иначе челлендж отменяется, а ставки возвращаются.
                </p>
                @if ($participating)
                    <form method="post" action="{{ route('challenges.leave', $challenge) }}" class="mt-3">
                        @csrf
                        <button class="text-sm text-gray-600 underline hover:text-gray-900">Выйти из челленджа</button>
                    </form>
                @endif
            @endif

            @if ($challenge->status === 'completed')
                <p class="mt-4 text-sm text-gray-700">
                    Фонд потерянных ставок: <x-money :amount="$challenge->forfeit_pool ?? 0" />,
                    доля каждого, кто дошёл до конца: <x-money :amount="$challenge->survivor_share ?? 0" />.
                </p>
            @endif
        </x-card>

        <x-card title="Участники">
            @php($visible = $participants->where('status', '!=', 'left'))
            @if ($visible->isEmpty())
                <p class="text-gray-600">Пока никто не вступил.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($visible as $participant)
                        <li class="py-2 flex justify-between gap-4">
                            <span>{{ $participant->user->name }}</span>
                            <span class="text-gray-600">{{ $participant->statusLabel() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
