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
        <div class="min-h-screen flex flex-col">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white border-b border-gray-200">
                    <div class="max-w-5xl mx-auto py-5 px-4 sm:px-6">
                        <h1 class="text-xl font-semibold">{{ $header }}</h1>
                    </div>
                </header>
            @endisset

            <main class="flex-1">
                <div class="max-w-5xl mx-auto py-8 px-4 sm:px-6">
                    @if (session('status'))
                        <div class="mb-6 border border-emerald-200 bg-emerald-50 text-emerald-800 rounded-md px-4 py-3">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </main>

            @include('layouts.footer')
        </div>
    </body>
</html>
