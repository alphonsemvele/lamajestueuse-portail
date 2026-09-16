<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title inertia>{{ config('app.name', 'La Majestueuse') }}</title>

    {{-- Applique le theme avant le premier rendu pour eviter tout clignotement. --}}
    <script>
        (function () {
            var stored = localStorage.getItem('theme') || 'system';
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', stored === 'dark' || (stored === 'system' && prefersDark));
        })();
    </script>

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="min-h-dvh font-sans antialiased">
    @inertia
</body>
</html>
