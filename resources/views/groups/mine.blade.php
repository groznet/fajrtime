<x-app-layout title="Мои группы">
    <x-slot name="header">Мои группы</x-slot>

    <div class="space-y-6">
        <div>
            <a href="{{ route('groups.create') }}" class="inline-flex px-4 py-2 rounded-md bg-emerald-700 text-white text-sm hover:bg-emerald-800">Создать группу</a>
        </div>

        @if ($groups->isEmpty())
            <x-card>
                <p class="text-gray-600">Вы пока не состоите ни в одной группе. Найдите её в <a href="{{ route('groups.index') }}" class="underline">каталоге</a> или создайте свою.</p>
            </x-card>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($groups as $group)
                    <x-group-card :group="$group" />
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
