<x-app-layout title="Создать группу">
    <x-slot name="header">Создать группу</x-slot>

    <x-card class="max-w-2xl">
        <form method="post" action="{{ route('groups.store') }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="name" value="Название" />
                <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" maxlength="150" required />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="description" value="Описание" />
                <textarea id="description" name="description" rows="3" maxlength="2000"
                    class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md">{{ old('description') }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('description')" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2" x-data="{ stake: {{ (int) old('stake', 500) }} }">
                <div>
                    <x-input-label for="stake" value="Ставка, ₽" />
                    <x-text-input id="stake" name="stake" type="number" class="mt-1 block w-full" x-model.number="stake"
                        min="{{ $stakeMin }}" max="{{ $stakeMax }}" step="1" required />
                    <p class="mt-1 text-xs text-gray-500">От {{ number_format($stakeMin, 0, '', ' ') }} до {{ number_format($stakeMax, 0, '', ' ') }} ₽. Блокируется на балансе участника при вступлении.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('stake')" />
                </div>

                <div>
                    <x-input-label for="cycle_days" value="Длительность челленджа" />
                    <x-select-input id="cycle_days" name="cycle_days" class="mt-1 block w-full" required
                        :options="collect($cycleDays)->mapWithKeys(fn ($d) => [$d => $d.' дней'])->all()" :selected="old('cycle_days', 7)" />
                    <x-input-error class="mt-2" :messages="$errors->get('cycle_days')" />
                </div>
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">Видимость</legend>
                <div class="mt-2 space-y-2 text-sm">
                    <label class="flex items-start gap-2">
                        <input type="radio" name="visibility" value="public" class="mt-0.5 text-emerald-700 focus:ring-emerald-600" @checked(old('visibility', 'public') === 'public')>
                        <span>Открытая — группа видна в каталоге, вступить может любой</span>
                    </label>
                    <label class="flex items-start gap-2">
                        <input type="radio" name="visibility" value="private" class="mt-0.5 text-emerald-700 focus:ring-emerald-600" @checked(old('visibility') === 'private')>
                        <span>Закрытая — вступить можно только по ссылке-приглашению</span>
                    </label>
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('visibility')" />
            </fieldset>

            <x-primary-button>Создать группу</x-primary-button>
        </form>
    </x-card>
</x-app-layout>
