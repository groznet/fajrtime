@props(['active' => false])

<a {{ $attributes->merge(['class' => $active ? 'font-medium text-emerald-800' : 'text-gray-600 hover:text-gray-900']) }}>{{ $slot }}</a>
