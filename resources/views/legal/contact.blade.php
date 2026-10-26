<x-app-layout title="Контакты">
    <x-slot name="header">Контакты</x-slot>

    <div class="space-y-6">
        <x-card title="Сведения о владельце сайта">
            <x-operator-info />
        </x-card>

        <x-card title="Связаться с нами">
            <p class="text-gray-700">
                По вопросам работы сервиса, платежей и персональных данных пишите на
                <a href="mailto:{{ config('fajr.operator.email') }}" class="underline text-emerald-800">{{ config('fajr.operator.email') }}</a>
                @if (config('fajr.operator.telegram'))
                    или в Telegram: {{ config('fajr.operator.telegram') }}
                @endif.
                Обращения рассматриваются в срок до 30 дней.
            </p>
        </x-card>

        <x-card title="Жалобы на контент и нарушение прав">
            <p class="text-gray-700">
                Заявления о нарушении авторских и (или) смежных прав, а также жалобы на размещённую на сайте информацию
                (ст. 15.7 Федерального закона № 149-ФЗ «Об информации, информационных технологиях и о защите информации»)
                направляйте на <a href="mailto:{{ config('fajr.operator.email') }}" class="underline text-emerald-800">{{ config('fajr.operator.email') }}</a>.
                В заявлении укажите ваши данные, адрес страницы с материалом и суть претензии.
            </p>
        </x-card>
    </div>
</x-app-layout>
