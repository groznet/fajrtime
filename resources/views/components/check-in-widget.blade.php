@props(['state'])

{{-- Alpine component "checkIn" is defined in resources/js/app.js. The initial state never contains the code before it is revealed. --}}
<div x-data="checkIn({ stateUrl: '{{ route('check-in.state') }}', submitUrl: '{{ route('check-in.store') }}', initial: @js($state) })" x-cloak>
    <template x-if="state.status === 'none'">
        <p class="text-gray-600">На сегодня проверки нет. Она появится за несколько часов до Фаджра.</p>
    </template>

    <template x-if="state.status === 'waiting'">
        <div>
            <p class="text-gray-700">
                Код появится в случайный момент между
                <span class="font-semibold tabular-nums" x-text="state.range_from"></span> и
                <span class="font-semibold tabular-nums" x-text="state.range_to"></span>.
            </p>
            <p class="mt-3 flex items-center gap-2 text-emerald-800">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-600 animate-pulse"></span>
                Ждём код… Оставайтесь на этой странице, она обновляется сама.
            </p>
        </div>
    </template>

    <template x-if="state.status === 'revealed' || state.status === 'locked'">
        <div>
            <template x-if="state.status === 'revealed'">
                <div>
                    <p class="text-sm text-gray-600">Ваш код — введите его ниже:</p>
                    <p class="mt-1 text-4xl font-semibold tracking-widest tabular-nums" x-text="state.code"></p>
                    <p class="mt-1 text-sm text-gray-600">
                        Осталось <span class="font-medium tabular-nums" x-text="remaining"></span>
                    </p>
                </div>
            </template>
            <template x-if="state.status === 'locked'">
                <p class="text-red-700">Слишком много неверных попыток — подтвердить подъём сегодня уже нельзя.</p>
            </template>

            <form class="mt-4 flex flex-wrap items-start gap-3" @submit.prevent="submit()" x-show="state.status === 'revealed'">
                <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" x-model="code"
                    class="w-40 text-center text-xl tabular-nums tracking-widest border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md">
                <button type="submit" :disabled="busy || code.length !== 6"
                    class="inline-flex items-center px-4 py-2 bg-emerald-700 border border-emerald-700 rounded-md font-medium text-sm text-white hover:bg-emerald-800 disabled:opacity-50">
                    Подтвердить
                </button>
            </form>
            <p class="mt-2 text-xs text-gray-500" x-show="state.status === 'revealed'">
                Осталось попыток: <span x-text="state.attempts_left"></span>
            </p>
        </div>
    </template>

    <template x-if="state.status === 'confirmed'">
        <p class="text-lg font-medium text-emerald-800">✓ Подъём подтверждён. Да примет Аллах ваш намаз!</p>
    </template>

    <template x-if="state.status === 'missed'">
        <p class="text-red-700">
            Время на ввод кода вышло. Если вы участвуете в челленджах, день засчитан как пропущенный.
        </p>
    </template>

    <p class="mt-3 text-sm" :class="error ? 'text-red-600' : 'text-emerald-800'" x-show="message" x-text="message" role="status"></p>
</div>
