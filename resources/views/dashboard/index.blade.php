<x-app-layout title="Панель">
    <x-slot name="header">Ас-саляму алейкум, {{ $user->name }}</x-slot>

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
