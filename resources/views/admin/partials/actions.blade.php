@if ($actions->isEmpty())
    <p class="text-gray-600">Действий пока не было.</p>
@else
    <ul class="divide-y divide-gray-100 text-sm">
        @foreach ($actions as $action)
            <li class="py-2">
                <p>
                    <span class="font-medium">{{ $action->label() }}</span>
                    <span class="text-gray-500">· {{ $action->subject_type }} #{{ $action->subject_id }} · {{ $action->admin->name }}</span>
                </p>
                @if ($action->reason)
                    <p class="text-gray-600">Причина: {{ $action->reason }}</p>
                @endif
                <p class="text-xs text-gray-500">{{ $action->created_at->setTimezone(auth()->user()->timezone)->format('d.m.Y H:i') }}</p>
            </li>
        @endforeach
    </ul>
@endif
