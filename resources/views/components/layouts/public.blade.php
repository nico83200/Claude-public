<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2f6347">
    <meta name="description" content="Application de gestion équine : santé, soins, séances, demi-pensions, écuries. Fonctionne hors ligne.">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $appName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold text-brand-800"><img src="/icons/icon.svg" alt="" class="size-8"> {{ $appName }}</a>
            <nav class="flex items-center gap-2 text-sm">
                <a href="{{ route('pricing') }}" class="btn-ghost hidden sm:inline-flex">Tarifs</a>
                @auth<a href="{{ route('dashboard') }}" class="btn-primary">Mon espace</a>@else<a href="{{ route('login') }}" class="btn-ghost">Connexion</a><a href="{{ route('register') }}" class="btn-primary">Essayer</a>@endauth
            </nav>
        </div>
    </header>
    <main>{{ $slot }}</main>
    <footer class="border-t border-slate-200 bg-white py-8 text-center text-sm text-slate-500">
        <a href="{{ route('legal.privacy') }}" class="hover:underline">Confidentialité</a> · <a href="{{ route('legal.terms') }}" class="hover:underline">Conditions d'utilisation</a> · <a href="{{ route('pricing') }}" class="hover:underline">Tarifs</a>
        <p class="mt-2 text-xs">{{ $appName }} n'est pas un outil de diagnostic vétérinaire.</p>
    </footer>
</body>
</html>
