<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SOC GPT Assistant</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-100 font-sans">
    <div class="min-h-screen flex">
        @livewire('navigation-menu')
        <div class="flex-1 p-8">
            <h1 class="text-2xl font-bold mb-6">SOC GPT Assistant</h1>

        </div>
    </div>
    @livewireScripts
</body>
</html>
