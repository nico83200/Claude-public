<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2f6347">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $appName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh">
    <div class="flex min-h-dvh flex-col items-center px-4 py-10">
        <a href="{{ url('/') }}" class="mb-6 flex items-center gap-2 text-xl font-bold text-brand-800"><img src="/icons/icon.svg" alt="" class="size-9"> {{ $appName }}</a>
        <div class="w-full {{ $wide ?? false ? 'max-w-3xl' : 'max-w-md' }}">
            <x-flash />
            <div class="card p-6 sm:p-8">{{ $slot }}</div>
        </div>
        <p class="mt-6 text-center text-xs text-slate-500"><a href="{{ route('legal.privacy') }}" class="hover:underline">Confidentialité</a> · <a href="{{ route('legal.terms') }}" class="hover:underline">Conditions</a></p>
    </div>
</body>
</html>
