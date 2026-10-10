<!DOCTYPE html>
<html lang="fr">
<head>
        <meta name="description" content="Jack&amp;Cie — Toute la vie de votre cheval, réunie : santé, soins, séances, demi-pensions, écuries. Fonctionne hors ligne.">
    @include('partials.head')
</head>
<body class="min-h-dvh">
    <header class="border-b border-sand-200 bg-sand-50/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
            <a href="{{ route('home') }}" aria-label="Accueil"><x-logo /></a>
            <nav class="flex items-center gap-2 text-sm">
                <a href="{{ route('app.install') }}" class="btn-ghost hidden sm:inline-flex">L'application</a>
                <a href="{{ route('pricing') }}" class="btn-ghost hidden sm:inline-flex">Tarifs</a>
                @auth<a href="{{ route('dashboard') }}" class="btn-primary">Mon espace</a>@else<a href="{{ route('login') }}" class="btn-ghost">Connexion</a><a href="{{ route('register') }}" class="btn-primary">Essayer</a>@endauth
            </nav>
        </div>
    </header>
    <main>{{ $slot }}</main>
    <footer class="mt-20 border-t border-sand-200 py-10 text-center text-sm text-slate-500">
        <a href="{{ route('legal.privacy') }}" class="hover:underline">Confidentialité</a> · <a href="{{ route('legal.terms') }}" class="hover:underline">Conditions d'utilisation</a> · <a href="{{ route('pricing') }}" class="hover:underline">Tarifs</a> · <a href="{{ route('app.install') }}" class="hover:underline">Installer l'application</a>
        <img src="/brand/jackcie-signature.png" alt="Toute la vie de votre cheval, réunie." class="mx-auto mt-5 h-3 w-auto opacity-70">
        <p class="mt-3 text-xs">{{ $appName }} n'est pas un outil de diagnostic vétérinaire.</p>
    </footer>
    @include('partials.install', ['bannerBottom' => 'bottom-[calc(1rem+env(safe-area-inset-bottom))]'])
</body>
</html>
