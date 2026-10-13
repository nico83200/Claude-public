<!DOCTYPE html>
<html lang="fr">
<head>
    <meta name="robots" content="noindex, nofollow">
    @include('partials.head', ['title' => 'Urgence · '.$horse->shortName()])
</head>
<body class="min-h-dvh bg-slate-50">
    <main class="mx-auto max-w-xl px-4 py-6">
        <div class="mb-5 flex items-center gap-4">
            @if ($sheet['has_photo'])
                <img src="{{ route('emergency.photo', $token) }}" alt="" class="size-20 rounded-2xl object-cover">
            @endif
            <div>
                <p class="text-sm font-semibold tracking-wide text-red-700 uppercase">Fiche d'urgence</p>
                <h1 class="text-3xl font-bold">{{ $horse->shortName() }}</h1>
                @if ($horse->usual_name)<p class="text-sm text-slate-500">{{ $horse->official_name }}</p>@endif
            </div>
        </div>
        @include('emergency._sheet')
        <p class="mt-8 text-center text-xs text-slate-500">Fiche publiée par le propriétaire avec <a href="{{ url('/') }}" class="underline">Jack&amp;Cie</a>. Informations utiles en cas d'urgence uniquement.</p>
    </main>
</body>
</html>
