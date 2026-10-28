<footer class="border-t border-gray-200 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-6 space-y-3 text-sm text-gray-500">
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            <a href="{{ route('how-it-works') }}" class="hover:text-gray-900">Как это работает</a>
            <a href="{{ route('contact') }}" class="hover:text-gray-900">Контакты</a>
            <a href="{{ route('terms') }}" class="hover:text-gray-900">Пользовательское соглашение</a>
            <a href="{{ route('privacy') }}" class="hover:text-gray-900">Политика конфиденциальности</a>
            <a href="{{ route('offer') }}" class="hover:text-gray-900">Публичная оферта</a>
            <a href="{{ route('requisites') }}" class="hover:text-gray-900">Реквизиты</a>
            <a href="{{ route('consent') }}" class="hover:text-gray-900">Согласие на обработку ПДн</a>
        </div>
        <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs">
            <span>&copy; {{ date('Y') }} {{ config('fajr.operator.legal_name') ?: config('app.name') }}</span>
            <span>{{ config('fajr.operator.address') }}</span>
            <a href="mailto:{{ config('fajr.operator.email') }}" class="hover:text-gray-900">{{ config('fajr.operator.email') }}</a>
            @if (config('fajr.operator.phone'))
                <span>{{ config('fajr.operator.phone') }}</span>
            @endif
            @if (config('fajr.operator.tax_id'))
                <span>ИНН {{ config('fajr.operator.tax_id') }}</span>
            @endif
        </div>
    </div>
</footer>
