<x-guest-layout title="Вход">
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Пароль" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="inline-flex items-center">
            <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-600" name="remember">
            <span class="ms-2 text-sm text-gray-600">Запомнить меня</span>
        </label>

        <div class="flex items-center justify-between">
            <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('password.request') }}">Забыли пароль?</a>
            <x-primary-button>Войти</x-primary-button>
        </div>

        <p class="text-sm text-gray-600">
            Нет аккаунта? <a class="underline hover:text-gray-900" href="{{ route('register') }}">Зарегистрироваться</a>
        </p>
    </form>
</x-guest-layout>
