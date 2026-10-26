@props(['group'])

<a href="{{ route('groups.show', $group) }}" class="block bg-white border border-gray-200 rounded-lg p-4 hover:border-emerald-600">
    <div class="flex items-start justify-between gap-3">
        <h3 class="font-medium text-gray-900">{{ $group->name }}</h3>
        @if ($group->visibility === 'private')
            <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">закрытая</span>
        @endif
    </div>
    @if ($group->description)
        <p class="mt-1 text-sm text-gray-600 line-clamp-2">{{ $group->description }}</p>
    @endif
    <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-gray-600">
        <div>Ставка: <x-money :amount="$group->stake_amount" class="text-gray-900" /></div>
        <div>{{ $group->cycle_days }} дн.</div>
        <div>Участников: {{ $group->members_count }}</div>
    </dl>
    @if ($group->openChallenge)
        <p class="mt-2 text-sm text-emerald-800">
            {{ $group->openChallenge->statusLabel() }} · старт {{ $group->openChallenge->start_date->translatedFormat('j F') }}
        </p>
    @endif
</a>
