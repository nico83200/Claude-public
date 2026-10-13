<!DOCTYPE html>
<html lang="fr">
<head>
    <meta name="robots" content="noindex, nofollow">
    @include('partials.head', ['title' => 'Résumé de santé · '.$share->horse->shortName()])
    <style>@media print { .no-print { display: none !important; } body { background: #fff; } .card { box-shadow: none; border: 1px solid #e5e7eb; } }</style>
</head>
<body class="min-h-dvh bg-slate-50">
    <main class="mx-auto max-w-3xl px-4 py-6">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-semibold tracking-wide text-brand-700 uppercase">Résumé de santé{{ $share->recipient ? ' · pour '.$share->recipient : '' }}</p>
                <h1 class="text-3xl font-bold">{{ $share->horse->shortName() }}</h1>
                <p class="text-sm text-slate-500">Du {{ $s['from']->format('d/m/Y') }} au {{ now()->format('d/m/Y') }} · données saisies par le propriétaire et son entourage</p>
            </div>
            <button type="button" onclick="window.print()" class="btn-secondary no-print">Imprimer</button>
        </div>
        @if ($share->ai_summary)
            <section class="card mb-4 border-l-4 border-brand-500 p-5" data-vet-ai-summary>
                <h2 class="mb-1 font-semibold">Synthèse</h2>
                <p class="text-sm whitespace-pre-line text-slate-700">{{ $share->ai_summary }}</p>
                <p class="mt-2 text-xs text-slate-500">Rédigée automatiquement à partir du dossier ; les détails ci-dessous font foi.</p>
            </section>
        @endif
        @include('health._vet-summary-body')
        <p class="mt-8 text-center text-xs text-slate-500">Lien valable jusqu'au {{ $share->expires_at->format('d/m/Y à H:i') }} · <a href="{{ url('/') }}" class="underline">Jack&amp;Cie</a></p>
    </main>
</body>
</html>
