<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CCTV Dashboard') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-blue-600 min-h-screen">
        <div class="min-h-screen flex flex-col items-center pt-20 sm:pt-24 px-4">
            <div class="mb-2 text-center">
                <h1 class="text-2xl font-bold text-white">CCTV Dashboard</h1>
                <p class="text-blue-200 text-sm mt-1">Foot traffic monitoring system</p>
            </div>

            <div class="w-full sm:max-w-md px-6 py-8 bg-white shadow-xl rounded-xl overflow-hidden">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>