{{-- Contenu du résumé pour le vétérinaire (aperçu dans le dossier et page partagée). --}}
@php $obsColors = ['info' => 'slate', 'watch' => 'amber', 'alert' => 'red']; @endphp
<div class="space-y-4" data-vet-summary>
    @if ($s['identity'] || $s['precautions'])
        <section class="card p-5">
            <h2 class="mb-2 font-semibold">Identité</h2>
            @if ($s['identity'])<x-dl :items="$s['identity']" />@endif
            @if ($s['precautions'])<p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900"><strong>Précautions :</strong> {{ $s['precautions'] }}</p>@endif
        </section>
    @endif
    <section class="card p-5">
        <h2 class="mb-2 font-semibold">Traitements</h2>
        @forelse ($s['treatments'] as $t)
            <div class="border-t border-slate-100 py-2 text-sm first:border-0"><span class="font-medium">{{ $t->product }}</span>{{ $t->dosage ? ' — '.$t->dosage : '' }}
                <span class="block text-slate-500">Du {{ $t->starts_on->format('d/m/Y') }}{{ $t->ends_on ? ' au '.$t->ends_on->format('d/m/Y') : '' }} · {{ \App\Models\Treatment::STATUSES[$t->status] ?? $t->status }}{{ $t->frequency ? ' · '.$t->frequency : '' }}</span></div>
        @empty
            <p class="text-sm text-slate-500">Aucun traitement sur la période.</p>
        @endforelse
    </section>
    <section class="card p-5">
        <h2 class="mb-2 font-semibold">Soins et visites</h2>
        @forelse ($s['care'] as $c)
            <div class="border-t border-slate-100 py-2.5 text-sm first:border-0" data-vet-care>
                <p><span class="font-medium">{{ $c->performed_at->format('d/m/Y') }} · {{ $c->category?->name }}</span>{{ $c->professional ? ' · '.$c->professional->fullName() : '' }}</p>
                @if ($c->reason)<p class="text-slate-600">Motif : {{ $c->reason }}</p>@endif
                @if ($c->diagnosis)<p class="text-slate-600">Diagnostic communiqué : {{ $c->diagnosis }}</p>@endif
                @if ($c->instructions)<p class="text-slate-600">Consignes : {{ $c->instructions }}</p>@endif
            </div>
        @empty
            <p class="text-sm text-slate-500">Aucun soin noté sur la période.</p>
        @endforelse
        @if ($s['upcoming']->isNotEmpty())
            <p class="mt-3 text-sm text-slate-600"><strong>Prévu :</strong> {{ $s['upcoming']->map(fn ($c) => $c->category?->name.' le '.$c->next_check_on->format('d/m/Y'))->implode(' · ') }}</p>
        @endif
    </section>
    <section class="card p-5">
        <h2 class="mb-2 font-semibold">Poids et état corporel</h2>
        @if ($s['weights']->isNotEmpty())
            @if ($s['weight_change'] !== null)<p class="mb-2 text-sm">Évolution sur la période : <strong>{{ $s['weight_change'] > 0 ? '+' : '' }}{{ number_format($s['weight_change'], 1, ',', ' ') }} kg</strong></p>@endif
            <div class="grid grid-cols-1 gap-x-6 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($s['weights'] as $w)<div class="flex justify-between border-b border-slate-100 py-1"><span class="text-slate-500">{{ $w->measured_on->format('d/m/Y') }}</span><span>{{ $w->weight_kg ? number_format($w->weight_kg, 0, ',', ' ').' kg' : '' }}{{ $w->body_condition ? ' · '.$w->body_condition.'/9' : '' }}</span></div>@endforeach
            </div>
        @else
            <p class="text-sm text-slate-500">Aucune pesée sur la période.</p>
        @endif
    </section>
    <section class="card p-5">
        <h2 class="mb-2 font-semibold">Observations et anomalies en séance</h2>
        @forelse ($s['observations'] as $o)
            <p class="border-t border-slate-100 py-2 text-sm first:border-0"><x-badge :color="$obsColors[$o->severity] ?? 'slate'">{{ \App\Models\HealthObservation::SEVERITIES[$o->severity] ?? $o->severity }}</x-badge> {{ $o->observed_at->format('d/m/Y') }} — {{ $o->body }}</p>
        @empty
        @endforelse
        @foreach ($s['anomalies'] as $a)
            <p class="border-t border-slate-100 py-2 text-sm first:border-0"><x-badge color="amber">Séance</x-badge> {{ $a->scheduled_at->format('d/m/Y') }} — {{ $a->anomalies }}</p>
        @endforeach
        @if ($s['observations']->isEmpty() && $s['anomalies']->isEmpty())<p class="text-sm text-slate-500">Rien de signalé sur la période.</p>@endif
    </section>
</div>
