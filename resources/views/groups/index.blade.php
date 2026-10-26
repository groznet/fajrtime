<x-app-layout title="Группы">
    <x-slot name="header">Группы</x-slot>

    <div class="space-y-6">
        <form method="get" class="flex flex-wrap items-end gap-4">
            <div>
                <x-input-label for="cycle_days" value="Длительность" />
                <x-select-input id="cycle_days" name="cycle_days" class="mt-1 block"
                    :options="collect(config('fajr.cycle_days'))->mapWithKeys(fn ($d) => [$d => $d.' дн.'])->all()"
                    :selected="$filters['cycle_days'] ?? null" placeholder="Любая" />
            </div>
            <div>
                <x-input-label for="stake_from" value="Ставка от, ₽" />
                <x-text-input id="stake_from" name="stake_from" type="number" min="0" class="mt-1 block w-28" :value="$filters['stake_from'] ?? ''" />
            </div>
            <div>
                <x-input-label for="stake_to" value="до, ₽" />
                <x-text-input id="stake_to" name="stake_to" type="number" min="0" class="mt-1 block w-28" :value="$filters['stake_to'] ?? ''" />
            </div>
            <x-primary-button>Найти</x-primary-button>
            @auth
                <a href="{{ route('groups.create') }}" class="ml-auto text-sm underline text-emerald-800">Создать группу</a>
            @endauth
        </form>

        @if ($groups->isEmpty())
            <x-card><p class="text-gray-600">Подходящих групп пока нет.</p></x-card>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($groups as $group)
                    <x-group-card :group="$group" />
                @endforeach
            </div>
            {{ $groups->links() }}
        @endif
    </div>
</x-app-layout>
