<footer class="border-t border-gray-200 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-500">
        <span>&copy; {{ date('Y') }} {{ config('app.name') }}</span>
        <a href="{{ route('faq') }}" class="hover:text-gray-900">Вопросы и ответы</a>
        <a href="{{ route('contact') }}" class="hover:text-gray-900">Контакты</a>
        <a href="{{ route('terms') }}" class="hover:text-gray-900">Условия</a>
        <a href="{{ route('privacy') }}" class="hover:text-gray-900">Конфиденциальность</a>
        <a href="{{ route('offer') }}" class="hover:text-gray-900">Оферта</a>
    </div>
</footer>
