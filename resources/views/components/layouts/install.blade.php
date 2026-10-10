@props(['step' => 1, 'title' => 'Installation'])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    @include('partials.head')
</head>
<body class="min-h-dvh">
<div class="mx-auto flex min-h-dvh max-w-2xl flex-col px-4 py-10">
    <div class="mx-auto mb-8 w-48"><x-brand-mark /></div>
    @php $steps = [1 => 'Prérequis', 2 => 'Base de données', 3 => 'Application', 4 => 'Administrateur', 5 => 'Terminé']; @endphp
    <ol class="mb-6 grid grid-cols-5 gap-2 text-center text-[11px] sm:text-xs" aria-label="Étapes de l'installation">
        @foreach ($steps as $n => $label)
            <li class="flex flex-col items-center gap-1.5" @if ($n === $step) aria-current="step" @endif>
                <span class="flex size-8 items-center justify-center rounded-full text-sm font-semibold {{ $n < $step ? 'bg-brand-800 text-sand-50' : ($n === $step ? 'bg-gold-300 text-brand-900 ring-4 ring-gold-100' : 'bg-sand-100 text-slate-400') }}">{!! $n < $step ? '&#10003;' : $n !!}</span>
                <span class="{{ $n === $step ? 'font-semibold text-brand-900' : 'text-slate-500' }}">{{ $label }}</span>
            </li>
        @endforeach
    </ol>
    <x-flash />
    <div class="card p-6 sm:p-8">
        <h1 class="mb-1 text-2xl">{{ $title }}</h1>
        {{ $slot }}
    </div>
    <p class="mt-6 text-center text-xs text-slate-400">Installation de Jack&amp;Cie · cette page se fermera définitivement une fois l'installation terminée.</p>
</div>
</body>
</html>
