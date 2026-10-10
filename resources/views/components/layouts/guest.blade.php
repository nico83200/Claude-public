<!DOCTYPE html>
<html lang="fr">
<head>
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.head')
</head>
<body class="min-h-dvh">
    <div class="flex min-h-dvh flex-col items-center px-4 py-10">
        <a href="{{ url('/') }}" class="mb-6 block w-52" aria-label="Accueil"><x-brand-mark /></a>
        <div class="w-full {{ $wide ?? false ? 'max-w-3xl' : 'max-w-md' }}">
            <x-flash />
            <div class="card p-6 sm:p-8">{{ $slot }}</div>
        </div>
        <p class="mt-6 text-center text-xs text-slate-500"><a href="{{ route('legal.privacy') }}" class="hover:underline">Confidentialité</a> · <a href="{{ route('legal.terms') }}" class="hover:underline">Conditions</a></p>
    </div>
</body>
</html>
