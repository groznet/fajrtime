@props(['title' => null])

<section {{ $attributes->merge(['class' => 'bg-white border border-gray-200 rounded-lg p-4 sm:p-6']) }}>
    @if ($title)
        <h2 class="text-lg font-medium text-gray-900 mb-4">{{ $title }}</h2>
    @endif
    {{ $slot }}
</section>
