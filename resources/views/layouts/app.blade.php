<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/images/logo.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles {{-- Correcto para Livewire v2 --}}
    @stack('styles') {{-- Para tus estilos personalizados de componentes --}}
</head>

<body class="font-sans antialiased bg-[#1b1f22]">

    <div class="min-h-full">

        @livewire('admin.sidebar')

        <main class="w-auto sm:ml-64">
            <x-banner />
            @if (isset($header))
            <div class="pt-8 px-6">
                {{ $header }}
            </div>
            @endif
            {{ $slot }}
        </main>
    </div>

    @livewire('notification')
    @stack('modals')

    @livewireScripts {{-- Correcto para Livewire v2 --}}
    
    {{-- AÑADIR ESTA LÍNEA JUSTO DESPUÉS DE @livewireScripts --}}
    @stack('scripts') 
</body>

</html>