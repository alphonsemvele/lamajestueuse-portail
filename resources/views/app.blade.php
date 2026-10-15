<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        /*
         * Les metadonnees de partage, posees par les pages publiques
         * (LA MAJESTUEUSE Info). WhatsApp et Facebook lisent ce HTML brut
         * sans executer le moindre script : l'apercu d'un lien se joue donc
         * ici, pas dans React.
         */
        $meta = $page['props']['meta'] ?? null;
    @endphp

    {{-- Le portail reste hors des moteurs ; la salle de presse, non. --}}
    <meta name="robots" content="{{ ! empty($meta['indexable']) ? 'index, follow' : 'noindex, nofollow' }}">

    <title inertia>{{ $meta['titre'] ?? config('app.name', 'La Majestueuse') }}</title>

    @if ($meta)
        <meta name="description" content="{{ $meta['description'] ?? '' }}">
        <link rel="canonical" href="{{ $meta['url'] }}">

        <meta property="og:site_name" content="{{ $meta['site'] ?? config('app.name') }}">
        <meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
        <meta property="og:locale" content="fr_FR">
        <meta property="og:url" content="{{ $meta['url'] }}">
        <meta property="og:title" content="{{ $meta['titre'] }}">
        <meta property="og:description" content="{{ $meta['description'] ?? '' }}">

        @if (! empty($meta['image']))
            <meta property="og:image" content="{{ $meta['image'] }}">
            <meta property="og:image:secure_url" content="{{ $meta['image'] }}">
            {{-- Dimensions reelles seulement : annoncees de travers, elles
                 degradent l'apercu au lieu de l'ameliorer. --}}
            @if (! empty($meta['imageLargeur']))
                <meta property="og:image:width" content="{{ $meta['imageLargeur'] }}">
                <meta property="og:image:height" content="{{ $meta['imageHauteur'] }}">
            @endif
            <meta property="og:image:alt" content="{{ $meta['titre'] }}">
        @endif

        @if (! empty($meta['publieLe']))
            <meta property="article:published_time" content="{{ $meta['publieLe'] }}">
        @endif

        <meta name="twitter:card" content="{{ ! empty($meta['image']) ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $meta['titre'] }}">
        <meta name="twitter:description" content="{{ $meta['description'] ?? '' }}">
        @if (! empty($meta['image']))
            <meta name="twitter:image" content="{{ $meta['image'] }}">
        @endif
    @endif

    {{-- La marque du groupe dans l'onglet et sur l'ecran d'accueil mobile. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('images/icone-la-majestueuse.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icone-la-majestueuse.png') }}">

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
