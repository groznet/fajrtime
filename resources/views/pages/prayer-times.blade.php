<x-app-layout title="Время намаза">
    <x-slot name="header">Время намаза</x-slot>

    <div class="max-w-md space-y-4">
        <form method="GET" action="{{ route('prayer-times') }}">
            <x-input-label for="city" value="Город" />
            <x-select-input id="city" name="city" class="mt-1 block w-full" :options="$cities" :selected="$city->id" onchange="this.form.submit()" />
            <noscript><x-primary-button class="mt-2">Показать</x-primary-button></noscript>
        </form>

        <x-card>
            <p class="text-sm text-gray-500 mb-2">{{ $city->name }}, {{ $today->translatedFormat('j F Y') }}</p>
            <x-prayer-times-table :times="$times" :timezone="$city->timezone" />
            <p class="mt-4 text-xs text-gray-500">
                Время местное ({{ $city->timezone }}). Метод расчёта: {{ $methodName }}.
            </p>
        </x-card>
    </div>
</x-app-layout>
