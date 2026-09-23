<x-guest-layout title="Подтверждение email">
    <p class="mb-4 text-sm text-gray-600">Мы отправили ссылку для подтверждения на ваш email. Если письмо не пришло, отправим ещё раз.</p>

    @if (session('status') == 'verification-link-sent')
        <p class="mb-4 text-sm text-emerald-700">Новая ссылка отправлена.</p>
    @endif

    <div class="flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>Отправить ещё раз</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900">Выйти</button>
        </form>
    </div>
</x-guest-layout>
