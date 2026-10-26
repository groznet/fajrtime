<x-app-layout title="Подтверждение подъёма">
    <x-slot name="header">Подтверждение подъёма</x-slot>

    <div class="max-w-2xl space-y-6">
        <x-card title="Фаджр">
            <x-check-in-widget :state="$state" />
        </x-card>

        @unless ($hasRunningChallenge)
            <x-card>
                <p class="text-gray-600">
                    Вы пока не участвуете в идущем челлендже. Проверка начнётся в первый день челленджа, в котором вы участвуете.
                    <a href="{{ route('groups.mine') }}" class="underline text-emerald-800">Мои группы</a>
                </p>
            </x-card>
        @endunless

        <p class="text-sm text-gray-500">
            Платформа не присылает напоминаний — заведите свой будильник. Код появляется в случайный момент в указанном
            промежутке и действует несколько минут, поэтому нужно бодрствовать и следить за страницей всё это время.
        </p>
    </div>
</x-app-layout>
