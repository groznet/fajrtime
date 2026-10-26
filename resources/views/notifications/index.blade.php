<x-app-layout title="Уведомления">
    <x-slot name="header">Уведомления</x-slot>

    <x-card>
        @if ($notifications->isEmpty())
            <p class="text-gray-600">Уведомлений пока нет.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($notifications as $notification)
                    <li class="py-3 {{ in_array($notification->id, $unread) ? 'font-medium' : '' }}">
                        <p>{{ $notification->data['title'] ?? '' }}</p>
                        <p class="text-sm font-normal text-gray-600">{{ $notification->data['body'] ?? '' }}</p>
                        <p class="mt-1 text-xs font-normal text-gray-500">
                            {{ $notification->created_at->setTimezone($user->timezone)->format('d.m.Y H:i') }}
                            @if (! empty($notification->data['url']))
                                · <a href="{{ $notification->data['url'] }}" class="underline">Открыть</a>
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $notifications->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
