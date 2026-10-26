@props(['title'])

<x-app-layout :title="$title">
    <x-slot name="header">{{ $title }}</x-slot>

    <x-card>
        <p class="text-sm text-gray-500 mb-6">Редакция от {{ \Illuminate\Support\Carbon::parse(config('fajr.legal_updated_at'))->translatedFormat('j F Y') }}</p>
        <div class="space-y-4 text-gray-700 leading-relaxed [&_h2]:text-lg [&_h2]:font-medium [&_h2]:text-gray-900 [&_h2]:pt-4 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:space-y-1 [&_a]:underline [&_a]:text-emerald-800">
            {{ $slot }}
        </div>
    </x-card>
</x-app-layout>
