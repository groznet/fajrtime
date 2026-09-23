<x-guest-layout title="Регистрация">
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="name" value="Имя" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" maxlength="100" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" maxlength="150" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="city_id" value="Город" />
            <x-select-input id="city_id" name="city_id" class="block mt-1 w-full" required
                :options="$cities" :selected="old('city_id')" placeholder="Выберите город" />
            <p class="mt-1 text-xs text-gray-500">По городу рассчитывается время Фаджра. Сменить город во время активного челленджа нельзя.</p>
            <x-input-error :messages="$errors->get('city_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Пароль" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Повторите пароль" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">Уже зарегистрированы?</a>
            <x-primary-button>Зарегистрироваться</x-primary-button>
        </div>
    </form>
</x-guest-layout>
