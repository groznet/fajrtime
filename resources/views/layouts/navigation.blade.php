<nav x-data="{ open: false }" class="bg-white border-b border-gray-200">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">
        <div class="flex justify-between items-center h-14">
            <a href="{{ auth()->check() ? route('dashboard') : route('home') }}" class="font-semibold text-lg text-emerald-800">
                {{ config('app.name') }}
            </a>

            <div class="hidden sm:flex items-center gap-6 text-sm">
                @auth
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Панель</x-nav-link>
                    <x-nav-link :href="route('groups.mine')" :active="request()->routeIs('groups.mine', 'groups.create')">Мои группы</x-nav-link>
                    <x-nav-link :href="route('groups.index')" :active="request()->routeIs('groups.index', 'groups.show')">Группы</x-nav-link>
                    <x-nav-link :href="route('wallet')" :active="request()->routeIs('wallet*')">Кошелёк</x-nav-link>
                    <x-nav-link :href="route('prayer-times')" :active="request()->routeIs('prayer-times')">Время намаза</x-nav-link>
                    <x-nav-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')">Настройки</x-nav-link>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-gray-600 hover:text-gray-900">Выйти</button>
                    </form>
                @else
                    <x-nav-link :href="route('groups.index')" :active="request()->routeIs('groups.index', 'groups.show')">Группы</x-nav-link>
                    <x-nav-link :href="route('how-it-works')" :active="request()->routeIs('how-it-works')">Как это работает</x-nav-link>
                    <x-nav-link :href="route('prayer-times')" :active="request()->routeIs('prayer-times')">Время намаза</x-nav-link>
                    <x-nav-link :href="route('login')">Войти</x-nav-link>
                    <a href="{{ route('register') }}" class="px-3 py-1.5 rounded-md bg-emerald-700 text-white hover:bg-emerald-800">Регистрация</a>
                @endauth
            </div>

            <button type="button" @click="open = ! open" class="sm:hidden p-2 text-gray-600" aria-label="Меню">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak class="sm:hidden border-t border-gray-200 px-4 py-3 space-y-2 flex flex-col">
        @auth
            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Панель</x-nav-link>
            <x-nav-link :href="route('groups.mine')" :active="request()->routeIs('groups.mine', 'groups.create')">Мои группы</x-nav-link>
                    <x-nav-link :href="route('groups.index')" :active="request()->routeIs('groups.index', 'groups.show')">Группы</x-nav-link>
                    <x-nav-link :href="route('wallet')" :active="request()->routeIs('wallet*')">Кошелёк</x-nav-link>
            <x-nav-link :href="route('prayer-times')" :active="request()->routeIs('prayer-times')">Время намаза</x-nav-link>
            <x-nav-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')">Настройки</x-nav-link>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-gray-600">Выйти</button>
            </form>
        @else
            <x-nav-link :href="route('groups.index')">Группы</x-nav-link>
            <x-nav-link :href="route('how-it-works')">Как это работает</x-nav-link>
            <x-nav-link :href="route('prayer-times')">Время намаза</x-nav-link>
            <x-nav-link :href="route('login')">Войти</x-nav-link>
            <x-nav-link :href="route('register')">Регистрация</x-nav-link>
        @endauth
    </div>
</nav>
