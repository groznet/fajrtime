<x-guest-layout title="Подтверждение пароля">
    <p class="mb-4 text-sm text-gray-600">Для продолжения введите пароль.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="password" value="Пароль" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end">
            <x-primary-button>Подтвердить</x-primary-button>
        </div>
    </form>
</x-guest-layout>
