<x-layouts.app title="Santé et soins">
    <x-page-header title="Santé et soins" subtitle="Vue d'ensemble de tous vos chevaux">
        <x-horse-picker key="care" :horses="$editHorses" route="horses.care.create" label="Soin" class="btn-primary" title="Soin pour quel cheval ?" />
        <x-horse-picker key="vet" :horses="$vetHorses" route="horses.vet-summary" icon="share" label="Partager avec le vétérinaire" hint="Son résumé de santé s'affiche ; vous pourrez ensuite l'envoyer par e-mail ou SMS au vétérinaire, avec un lien temporaire." />
    </x-page-header>
    @include('health._hub-tabs', ['healthHub' => ['tab' => 'sante']])
    @include('health._prescription-overview-card')
    <div class="grid gap-4 lg:grid-cols-2">
        <x-section title="Échéances (60 jours)">
            @forelse ($due as $c)
                <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 py-2 text-sm first:border-0" data-due="{{ $c->id }}">
                    <a href="{{ route('horses.care.show', [$c->horse_id, $c]) }}" class="flex min-w-0 basis-full items-center justify-between gap-2 sm:basis-0 sm:flex-1">
                        <span class="min-w-0"><span class="font-medium">{{ $c->horse->shortName() }}</span> — {{ $c->category->name }}<span class="block text-xs text-slate-500">{{ $c->professional?->fullName() }}</span></span>
                        <x-badge :color="$c->next_check_on->isPast() ? 'red' : (today()->diffInDays($c->next_check_on) < 14 ? 'amber' : 'slate')">{{ $c->next_check_on->format('d/m/Y') }}</x-badge>
                    </a>
                    @if (in_array($c->horse_id, $editableIds))
                        <a href="{{ route('horses.care.create', [$c->horse_id, 'suite' => $c->id]) }}" class="btn-secondary ml-auto min-h-11 shrink-0 px-3 text-xs" data-note-passage><x-icon name="check" class="size-4" /> Noter le passage</a>
                    @endif
                </div>
            @empty <p class="text-sm text-slate-500">Aucune échéance.</p> @endforelse
        </x-section>
        <x-section title="Points d'attention en cours">
            @forelse ($alerts as $o)
                <a href="{{ route('horses.observations.show', [$o->horse_id, $o]) }}" class="block border-t border-slate-100 py-2 text-sm first:border-0" data-overview-attention><x-badge :color="$o->severity === 'alert' ? 'red' : 'amber'">{{ \App\Models\HealthObservation::SEVERITIES[$o->severity] }}</x-badge> <span class="font-medium">{{ $o->horse->shortName() }}</span> {{ \Illuminate\Support\Str::limit($o->label(), 100) }}<span class="block text-xs text-slate-500">{{ $o->author?->name }} · {{ $o->observed_at->diffForHumans() }}</span></a>
            @empty <p class="text-sm text-slate-500">Aucun point d'attention en cours.</p> @endforelse
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
