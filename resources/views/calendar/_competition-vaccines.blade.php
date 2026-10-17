{{-- Concours : vaccins du cheval d'après le carnet de santé, selon le règlement choisi (App\Services\CompetitionVaccines). --}}
@php
    $cv = app(\App\Services\CompetitionVaccines::class);
    [$badgeColor, $badgeLabel] = \App\Services\CompetitionVaccines::badge($vaccines);
    $rule = $vaccines['rule'];
    $arrival = ! empty($rule['arrival']);
    $minDays = collect($vaccines['items'])->max('min_days');
    $iconFor = fn ($status) => match ($status) { 'ok' => ['check', 'text-emerald-600'], 'planned' => ['calendar', 'text-sky-600'], default => ['alert', 'text-red-600'] };
@endphp
<x-section title="Vaccins pour ce concours" data-competition-vaccines="{{ $vaccines['state'] }}">
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <x-badge :color="$badgeColor">{{ $badgeLabel }}</x-badge>
        <span class="text-xs text-slate-500">Règles {{ $rule['short'] }}</span>
    </div>
    <ul class="space-y-2.5 text-sm text-slate-800">
        @foreach ($vaccines['items'] as $item)
            @php
                [$icon, $iconColor] = $iconFor($item['status']);
            @endphp
            <li class="flex gap-2" data-vaccine="{{ $item['key'] }}" data-vaccine-status="{{ $item['status'] }}">
                <x-icon :name="$icon" class="mt-0.5 size-4 shrink-0 {{ $iconColor }}" />
                <span>{{ $cv->line($item, $vaccines) }}
                    @if ($item['single'] && $item['state'] === 'ok')<span class="mt-0.5 block text-xs text-slate-500">Une seule injection dans le carnet : si c'était la première d'une primo-vaccination, il en faut une deuxième.</span>@endif
                </span>
            </li>
        @endforeach
    </ul>
    @foreach ($vaccines['unknown'] as $u)
        <p class="mt-3 rounded-2xl bg-amber-50 px-3 py-2 text-sm text-amber-900" data-vaccine-unknown>Vaccination du {{ $u['date']->translatedFormat('j F Y') }} sans précision : indiquez les vaccins reçus.
            @if ($canEditHealth && $u['care'])<a href="{{ route('horses.care.edit', [$event->horse, $u['care']]) }}" class="font-medium underline">Préciser</a>@endif
        </p>
    @endforeach
    @if ($vaccines['deadline'])
        <p class="mt-3 text-sm font-medium {{ $vaccines['late'] ? 'text-red-700' : 'text-slate-900' }}" data-vaccine-deadline>
            @if ($vaccines['late'])
                Trop tard pour ce concours : il faut au moins {{ $minDays }} jours entre l'injection et {{ $arrival ? 'l\'arrivée sur le concours' : 'le concours' }}.
            @else
                À faire au plus tard le {{ $vaccines['deadline']->translatedFormat('l j F') }} ({{ $minDays }} jours avant {{ $arrival ? 'l\'arrivée sur le concours' : 'le concours' }}).
            @endif
        </p>
    @endif
    @if ($vaccineLink)
        <a href="{{ $vaccineLink }}" class="btn-primary mt-3 w-full" data-vaccine-appointment><x-icon name="calendar" class="size-4" /> Prendre rendez-vous avec le vétérinaire</a>
    @endif
    <details class="group mt-3 text-xs text-slate-500" data-vaccine-rules>
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between font-medium text-slate-600">Règles appliquées · {{ $rule['label'] }}<x-icon name="chevron" class="size-4 transition group-open:rotate-90" /></summary>
        <ul class="mt-1 space-y-1">
            @foreach ($rule['vaccines'] as $key => $p)
                <li>{{ \App\Support\Vaccines::TYPES[$key] }} : {{ \App\Support\VaccineRules::describe($p, $arrival) }}.</li>
            @endforeach
        </ul>
        @if (filled($rule['note'] ?? null))<p class="mt-2">{{ $rule['note'] }}</p>@endif
        <p class="mt-2">Source : {{ $rule['source'] }} Vérifiée le {{ \Illuminate\Support\Carbon::parse($rule['checked_on'])->format('d/m/Y') }}.</p>
        @if ($arrival)<p class="mt-1">La date du concours sert de date d'arrivée : comptez à partir de votre arrivée réelle si elle est plus tôt.</p>@endif
    </details>
    <p class="mt-2 text-xs text-slate-500">D'après le carnet de santé de {{ $event->horse->shortName() }} : le passeport du cheval fait foi.
        @if ($canEdit)<a href="{{ route('calendar.edit', $event) }}" class="link">Changer de règlement</a>@endif
    </p>
</x-section>
