<x-app-layout title="Челлендж">
    <x-slot name="header"><a href="{{ route('admin.index') }}" class="hover:underline">Админка</a> · {{ $challenge->group->name }}</x-slot>

    <div class="space-y-6">
        <x-card>
            <p class="font-medium">{{ $challenge->statusLabel() }}</p>
            <p class="mt-1 text-sm text-gray-600">
                {{ $challenge->start_date->translatedFormat('j F') }} — {{ $challenge->end_date->translatedFormat('j F Y') }}
                · ставка <x-money :amount="$challenge->stake_amount" />
                · <a href="{{ route('challenges.show', $challenge) }}" class="underline">страница челленджа</a>
            </p>
        </x-card>

        <x-card title="Участники">
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($participants as $participant)
                    <li class="py-3">
                        <div class="flex flex-wrap justify-between gap-2">
                            <p>
                                <span class="font-medium">{{ $participant->user->name }}</span>
                                <span class="text-gray-500">{{ $participant->user->email }}</span>
                            </p>
                            <p class="text-gray-600">
                                {{ $participant->statusLabel() }}
                                @if ($participant->eliminated_on) · выбыл(а) {{ $participant->eliminated_on->translatedFormat('j F') }} @endif
                            </p>
                        </div>

                        @if ($participant->status === 'eliminated' && $challenge->status === 'active')
                            <form method="post" action="{{ route('admin.participants.restore', $participant) }}" class="mt-2 flex flex-wrap items-start gap-2">
                                @csrf
                                <x-text-input name="reason" placeholder="Причина (сбой сайта, неверное время Фаджра…)" class="w-80 text-sm" required minlength="5" maxlength="500" />
                                <button class="px-3 py-2 rounded-md border border-gray-300 text-sm hover:bg-gray-50">Вернуть в челлендж</button>
                            </form>
                            <x-input-error class="mt-1" :messages="$errors->get('reason')" />
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card title="Журнал по этому челленджу">
            @include('admin.partials.actions', ['actions' => $actions])
        </x-card>
    </div>
</x-app-layout>
