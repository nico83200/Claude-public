{{-- Contenu de la fiche d'urgence (page publique et aperçu propriétaire). --}}
@php $tel = fn ($n) => preg_replace('/[^0-9+]/', '', (string) $n); @endphp
<div class="space-y-4" data-emergency-sheet>
    @if ($sheet['contact']['phone'])
        <a href="tel:{{ $tel($sheet['contact']['phone']) }}" class="flex items-center gap-4 rounded-3xl bg-red-700 p-5 text-white shadow-lg" data-emergency-call>
            <x-icon name="phone" class="size-8 shrink-0" />
            <span><span class="block text-sm text-white/80">Contact en cas d'urgence</span><span class="block text-xl font-bold">{{ $sheet['contact']['name'] ?: 'Appeler' }}</span><span class="block text-lg">{{ $sheet['contact']['phone'] }}</span></span>
        </a>
    @endif
    @if ($sheet['professionals']->isNotEmpty())
        <section class="card p-5">
            <h2 class="mb-2 font-semibold">Intervenants habituels</h2>
            @foreach ($sheet['professionals'] as $p)
                <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-2.5 first:border-0">
                    <span><span class="block font-medium">{{ $p->fullName() }}</span><span class="text-sm text-slate-500">{{ $p->kindLabel() }}</span></span>
                    @if ($p->phone)<a href="tel:{{ $tel($p->phone) }}" class="btn-secondary min-h-10 shrink-0 px-4"><x-icon name="phone" class="size-4" /> {{ $p->phone }}</a>@endif
                </div>
            @endforeach
        </section>
    @endif
    @if ($sheet['precautions'] || $sheet['notes'])
        <section class="card border-l-4 border-amber-400 p-5">
            <h2 class="mb-2 font-semibold">À savoir</h2>
            @if ($sheet['notes'])<p class="whitespace-pre-line">{{ $sheet['notes'] }}</p>@endif
            @if ($sheet['precautions'])<p class="mt-2 whitespace-pre-line text-slate-700">{{ $sheet['precautions'] }}</p>@endif
        </section>
    @endif
    <section class="card p-5">
        <h2 class="mb-2 font-semibold">Traitements en cours</h2>
        @forelse ($sheet['treatments'] as $t)
            <p class="border-t border-slate-100 py-2 first:border-0"><span class="font-medium">{{ $t->product }}</span>@if ($t->dosage) — {{ $t->dosage }}@endif<span class="block text-sm text-slate-500">{{ $t->frequency }}{{ $t->ends_on ? ($t->frequency ? ' · ' : '').'jusqu\'au '.$t->ends_on->format('d/m/Y') : '' }}</span></p>
        @empty
            <p class="text-sm text-slate-500">Aucun traitement en cours.</p>
        @endforelse
    </section>
    @if ($sheet['identity'])
        <section class="card p-5">
            <h2 class="mb-2 font-semibold">Identification</h2>
            <x-dl :items="$sheet['identity']" />
        </section>
    @endif
</div>
