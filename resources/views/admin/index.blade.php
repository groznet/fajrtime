<x-app-layout title="Админка">
    <x-slot name="header">Админка</x-slot>

    <div class="space-y-6">
        <x-card title="Заявки на вывод">
            <p class="text-gray-700">
                Ожидают обработки: <span class="font-semibold">{{ $pendingWithdrawals }}</span>
                · <a href="{{ route('admin.withdrawals') }}" class="underline text-emerald-800">Открыть список</a>
            </p>
        </x-card>

        <x-card title="Идущие челленджи">
            @if ($challenges->isEmpty())
                <p class="text-gray-600">Сейчас нет челленджей, которые набирают участников или идут.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($challenges as $challenge)
                        <li class="py-2 flex flex-wrap justify-between gap-x-4">
                            <a href="{{ route('admin.challenges.show', $challenge) }}" class="underline">{{ $challenge->group->name }}</a>
                            <span class="text-gray-600">
                                {{ $challenge->statusLabel() }} · {{ $challenge->start_date->translatedFormat('j F') }} — {{ $challenge->end_date->translatedFormat('j F') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Журнал действий">
            @include('admin.partials.actions', ['actions' => $actions])
        </x-card>
    </div>
</x-app-layout>
