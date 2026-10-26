<x-app-layout :title="$group->name">
    <x-slot name="header">{{ $group->name }}</x-slot>

    <div class="space-y-6">
        <x-card>
            @if ($group->description)
                <p class="text-gray-700 whitespace-pre-line">{{ $group->description }}</p>
            @endif
            <dl class="mt-4 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-4">
                <div><dt class="text-gray-500">Ставка</dt><dd class="font-medium"><x-money :amount="$group->stake_amount" /></dd></div>
                <div><dt class="text-gray-500">Длительность</dt><dd class="font-medium">{{ $group->cycle_days }} дней</dd></div>
                <div><dt class="text-gray-500">Участников в группе</dt><dd class="font-medium">{{ $group->members_count }}</dd></div>
                <div><dt class="text-gray-500">Создатель</dt><dd class="font-medium">{{ $group->creator->name }}</dd></div>
            </dl>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                @guest
                    <a href="{{ route('register') }}" class="px-4 py-2 rounded-md bg-emerald-700 text-white text-sm hover:bg-emerald-800">Зарегистрироваться и вступить</a>
                    <a href="{{ route('login') }}" class="text-sm underline">Войти</a>
                @else
                    @if (! $isMember)
                        <form method="post" action="{{ route('groups.join', $group) }}">
                            @csrf
                            @if ($inviteToken) <input type="hidden" name="invite" value="{{ $inviteToken }}"> @endif
                            <x-primary-button>Вступить в группу</x-primary-button>
                        </form>
                    @elseif (! $isCreator)
                        <form method="post" action="{{ route('groups.leave', $group) }}">
                            @csrf
                            <button class="text-sm text-gray-600 underline hover:text-gray-900">Выйти из группы</button>
                        </form>
                    @endif
                @endguest
            </div>
        </x-card>

        @if ($isMember && ! $group->isPublic())
            <x-card title="Ссылка-приглашение">
                <p class="text-sm text-gray-600 mb-2">Отправьте эту ссылку тем, кого хотите пригласить в закрытую группу.</p>
                <input type="text" readonly value="{{ route('groups.invite', $group->invite_token) }}" onclick="this.select()"
                    class="w-full border-gray-300 rounded-md text-sm bg-gray-50">
            </x-card>
        @endif

        <x-card title="Челлендж">
            @if ($challenge)
                <p class="text-lg font-medium">{{ $challenge->statusLabel() }}</p>
                <p class="mt-1 text-gray-600">
                    {{ $challenge->start_date->translatedFormat('j F') }} — {{ $challenge->end_date->translatedFormat('j F Y') }},
                    участников: {{ $participantsCount }} (нужно минимум {{ $minParticipants }}, максимум {{ $maxParticipants }})
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <a href="{{ route('challenges.show', $challenge) }}" class="text-sm underline text-emerald-800">Подробнее</a>

                    @auth
                        @if ($challenge->isRecruiting() && $isMember)
                            @if ($participating)
                                <form method="post" action="{{ route('challenges.leave', $challenge) }}">
                                    @csrf
                                    <button class="text-sm text-gray-600 underline hover:text-gray-900">Выйти из челленджа</button>
                                </form>
                            @elseif ($topUpNeeded !== null)
                                <p class="text-sm text-gray-700">
                                    Для вступления нужно <x-money :amount="$challenge->stake_amount" />, у вас <x-money :amount="$user->balance_available" />.
                                    <a href="{{ route('wallet') }}" class="underline text-emerald-800">Пополнить минимум на <x-money :amount="$topUpNeeded" /></a>
                                    (с учётом комиссии).
                                </p>
                            @else
                                <form method="post" action="{{ route('challenges.join', $challenge) }}">
                                    @csrf
                                    <x-primary-button>Участвовать за <x-money :amount="$challenge->stake_amount" class="ml-1" /></x-primary-button>
                                </form>
                            @endif
                        @elseif ($participating)
                            <span class="text-sm text-emerald-800">Вы участвуете</span>
                        @endif
                    @endauth
                </div>
            @elseif ($isCreator)
                <form method="post" action="{{ route('challenges.store', $group) }}" class="flex flex-wrap items-end gap-4">
                    @csrf
                    <div>
                        <x-input-label for="start_date" value="Дата старта (первый день подъёма)" />
                        <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block"
                            :value="old('start_date', now(config('fajr.challenge_timezone'))->addDays(3)->toDateString())"
                            min="{{ now(config('fajr.challenge_timezone'))->addDay()->toDateString() }}" required />
                        <x-input-error class="mt-2" :messages="$errors->get('start_date')" />
                    </div>
                    <x-primary-button>Запустить набор</x-primary-button>
                </form>
                <p class="mt-3 text-sm text-gray-600">
                    Челлендж продлится {{ $group->cycle_days }} дней. Набор закрывается в 00:00 по Москве в день старта;
                    если к этому моменту наберётся меньше {{ $minParticipants }} участников, челлендж отменяется и ставки возвращаются.
                </p>
            @else
                <p class="text-gray-600">Сейчас в группе нет активного набора. Создатель группы запустит следующий челлендж.</p>
            @endif
        </x-card>

        @if ($pastChallenges->isNotEmpty())
            <x-card title="Прошлые челленджи">
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($pastChallenges as $past)
                        <li class="py-2 flex justify-between gap-4">
                            <a href="{{ route('challenges.show', $past) }}" class="underline">
                                {{ $past->start_date->translatedFormat('j F') }} — {{ $past->end_date->translatedFormat('j F Y') }}
                            </a>
                            <span class="text-gray-600">{{ $past->statusLabel() }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
</x-app-layout>
