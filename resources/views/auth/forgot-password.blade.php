<x-guest-layout title="Восстановление пароля">
    <p class="mb-4 text-sm text-gray-600">Укажите email, и мы отправим ссылку для сброса пароля.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex justify-end">
            <x-primary-button>Отправить ссылку</x-primary-button>
        </div>
    </form>
</x-guest-layout>
