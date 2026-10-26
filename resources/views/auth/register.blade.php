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

        <div class="space-y-3">
            <div>
                <label for="terms" class="inline-flex items-start gap-2 text-sm text-gray-600">
                    <input id="terms" type="checkbox" name="terms" value="1" required @checked(old('terms')) class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-600">
                    <span>
                        Я принимаю <a href="{{ route('terms') }}" target="_blank" class="underline hover:text-gray-900">Пользовательское соглашение</a>
                        и <a href="{{ route('offer') }}" target="_blank" class="underline hover:text-gray-900">Публичную оферту</a>.
                    </span>
                </label>
                <x-input-error :messages="$errors->get('terms')" class="mt-2" />
            </div>

            <div>
                <label for="consent" class="inline-flex items-start gap-2 text-sm text-gray-600">
                    <input id="consent" type="checkbox" name="consent" value="1" required @checked(old('consent')) class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-600">
                    <span>
                        Я даю <a href="{{ route('consent') }}" target="_blank" class="underline hover:text-gray-900">согласие на обработку персональных данных</a>.
                        С <a href="{{ route('privacy') }}" target="_blank" class="underline hover:text-gray-900">Политикой конфиденциальности</a> ознакомлен(а).
                    </span>
                </label>
                <x-input-error :messages="$errors->get('consent')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center justify-between">
            <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">Уже зарегистрированы?</a>
            <x-primary-button>Зарегистрироваться</x-primary-button>
        </div>
    </form>
</x-guest-layout>
