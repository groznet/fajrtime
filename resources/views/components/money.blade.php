@props(['amount'])

<span {{ $attributes->merge(['class' => 'tabular-nums whitespace-nowrap']) }}>{{ \App\Support\Money::format((int) $amount) }}</span>
