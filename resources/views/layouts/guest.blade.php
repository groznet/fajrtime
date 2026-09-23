<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' — ' : '' }}{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-50">
        <div class="min-h-screen flex flex-col items-center pt-10 px-4">
            <a href="{{ route('home') }}" class="font-semibold text-2xl text-emerald-800">{{ config('app.name') }}</a>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-white border border-gray-200 rounded-lg">
                @isset($title)
                    <h1 class="text-lg font-semibold mb-4">{{ $title }}</h1>
                @endisset

                {{ $slot }}
            </div>
        </div>
    </body>
</html>
