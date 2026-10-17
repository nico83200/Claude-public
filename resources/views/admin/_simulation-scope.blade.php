@php
    // Indicateurs et statistiques : part des simulations de charge, avec le lien pour les inclure ou les retirer.
    $simShare = \App\Services\LoadSimulation::weight();
    $simCounted = \App\Services\LoadSimulation::counted();
    $simQuery = fn (bool $with) => array_filter(request()->except('simulations') + ['simulations' => $with ? null : '0'], fn ($v) => $v !== null);
@endphp
@if ($simShare['users'] > 0)
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-2xl px-4 py-3 text-sm ring-1 {{ $simCounted ? 'bg-amber-50 text-amber-900 ring-amber-200' : 'bg-slate-50 text-slate-700 ring-slate-200' }}" data-simulation-scope="{{ $simCounted ? 'with' : 'without' }}">
        <span>
            @if ($simCounted)
                Simulations comptées : {{ \App\Support\Fr::count($simShare['users'], 'compte de test', 'comptes de test') }} et {{ \App\Support\Fr::count($simShare['horses'], 'cheval', 'chevaux') }} (avec leur activité) sont inclus dans ces chiffres.
            @else
                Simulations retirées : chiffres des seuls comptes réels.
            @endif
        </span>
        <a href="{{ route($route, $simQuery(! $simCounted)) }}" class="link font-medium">{{ $simCounted ? 'Voir sans les simulations' : 'Inclure les simulations' }}</a>
    </div>
@endif
