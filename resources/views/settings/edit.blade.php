<x-app-layout title="Настройки">
    <x-slot name="header">Настройки</x-slot>

    <div class="space-y-6 max-w-xl">
        <x-card title="Профиль">
            <form method="post" action="{{ route('settings.update') }}" class="space-y-4">
                @csrf
                @method('patch')

                <div>
                    <x-input-label for="name" value="Имя" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required maxlength="100" autocomplete="name" />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required maxlength="150" autocomplete="username" />
                    <x-input-error class="mt-2" :messages="$errors->get('email')" />
                </div>

                <div>
                    <x-input-label for="city_id" value="Город" />
                    <x-select-input id="city_id" name="city_id" class="mt-1 block w-full" required
                        :options="$cities" :selected="old('city_id', $user->city_id)" />
                    <p class="mt-1 text-xs text-gray-500">Часовой пояс: {{ $user->timezone }}. Сменить город во время активного челленджа нельзя.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('city_id')" />
                </div>

                <x-primary-button>Сохранить</x-primary-button>
            </form>
        </x-card>

        <x-card title="Пароль">
            <form method="post" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                @method('put')

                <div>
                    <x-input-label for="current_password" value="Текущий пароль" />
                    <x-text-input id="current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                    <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" value="Новый пароль" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" value="Повторите пароль" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                </div>

                <x-primary-button>Изменить пароль</x-primary-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
