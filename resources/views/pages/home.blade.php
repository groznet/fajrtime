<x-app-layout>
    <section class="py-8 max-w-2xl">
        <h1 class="text-3xl font-semibold leading-tight">Просыпайтесь на Фаджр вместе</h1>
        <p class="mt-4 text-lg text-gray-600">
            Групповые челленджи: участники вносят депозит и каждое утро подтверждают подъём кодом.
            Кто пропустил день — теряет депозит в пользу тех, кто выдержал.
        </p>
        <div class="mt-6 flex gap-4">
            @auth
                <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-md bg-emerald-700 text-white hover:bg-emerald-800">Перейти в панель</a>
            @else
                <a href="{{ route('register') }}" class="px-4 py-2 rounded-md bg-emerald-700 text-white hover:bg-emerald-800">Зарегистрироваться</a>
            @endauth
            <a href="{{ route('how-it-works') }}" class="px-4 py-2 rounded-md border border-gray-300 bg-white hover:bg-gray-50">Как это работает</a>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-3">
        <x-card>
            <p class="text-sm text-emerald-800 font-medium">Шаг 1</p>
            <p class="mt-1 font-medium">Пополните баланс</p>
            <p class="mt-1 text-sm text-gray-600">Выберите группу и внесите депозит.</p>
        </x-card>
        <x-card>
            <p class="text-sm text-emerald-800 font-medium">Шаг 2</p>
            <p class="mt-1 font-medium">Вставайте на Фаджр</p>
            <p class="mt-1 text-sm text-gray-600">Каждое утро в случайный момент появляется код — введите его за 5 минут.</p>
        </x-card>
        <x-card>
            <p class="text-sm text-emerald-800 font-medium">Шаг 3</p>
            <p class="mt-1 font-medium">Получите результат</p>
            <p class="mt-1 text-sm text-gray-600">Выдержавшие возвращают депозит и делят депозиты пропустивших.</p>
        </x-card>
    </section>
</x-app-layout>
