<x-layouts.app title="Santé et soins">
    <x-page-header title="Santé et soins" subtitle="Vue d'ensemble de tous vos chevaux" />
    <div class="grid gap-4 lg:grid-cols-2">
        <x-section title="Échéances (60 jours)">
            @forelse ($due as $c)
                <a href="{{ route('horses.care.show', [$c->horse_id, $c]) }}" class="flex items-center justify-between border-t border-slate-100 py-2 text-sm first:border-0">
                    <span><span class="font-medium">{{ $c->horse->shortName() }}</span> — {{ $c->category->name }}<span class="block text-xs text-slate-500">{{ $c->professional?->fullName() }}</span></span>
                    <x-badge :color="$c->next_check_on->isPast() ? 'red' : ($c->next_check_on->diffInDays() < 14 ? 'amber' : 'slate')">{{ $c->next_check_on->format('d/m/Y') }}</x-badge>
                </a>
            @empty <p class="text-sm text-slate-500">Aucune échéance.</p> @endforelse
        </x-section>
        <x-section title="Alertes non traitées">
            @forelse ($alerts as $o)
                <a href="{{ route('horses.care.index', $o->horse_id) }}" class="block border-t border-slate-100 py-2 text-sm first:border-0"><x-badge :color="$o->severity === 'alert' ? 'red' : 'amber'">{{ \App\Models\HealthObservation::SEVERITIES[$o->severity] }}</x-badge> <span class="font-medium">{{ $o->horse->shortName() }}</span> {{ \Illuminate\Support\Str::limit($o->body, 100) }}<span class="block text-xs text-slate-500">{{ $o->author?->name }} · {{ $o->observed_at->diffForHumans() }}</span></a>
            @empty <p class="text-sm text-slate-500">Aucune alerte.</p> @endforelse
        </x-section>
        <x-section title="Traitements en cours">
            @forelse ($treatments as $t)
                <a href="{{ route('horses.care.index', $t->horse_id) }}" class="block border-t border-slate-100 py-2 text-sm first:border-0"><span class="font-medium">{{ $t->horse->shortName() }}</span> — {{ $t->product }}<span class="block text-xs text-slate-500">{{ $t->dosage }} · {{ $t->ends_on ? 'fin le '.$t->ends_on->format('d/m') : 'sans date de fin' }}{{ $t->responsible ? ' · '.$t->responsible->name : '' }}</span></a>
            @empty <p class="text-sm text-slate-500">Aucun traitement en cours.</p> @endforelse
        </x-section>
        <x-section title="Soins récents">
            @forelse ($recent as $c)
                <a href="{{ route('horses.care.show', [$c->horse_id, $c]) }}" class="flex justify-between border-t border-slate-100 py-2 text-sm first:border-0"><span><span class="font-medium">{{ $c->horse->shortName() }}</span> — {{ $c->category->name }}</span><span class="text-xs text-slate-500">{{ $c->performed_at->format('d/m/Y') }}</span></a>
            @empty <p class="text-sm text-slate-500">Aucun soin.</p> @endforelse
        </x-section>
    </div>
</x-layouts.app>
