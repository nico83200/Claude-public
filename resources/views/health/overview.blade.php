<x-layouts.app title="Santé et soins">
    <x-page-header title="Santé et soins" subtitle="Vue d'ensemble de tous vos chevaux">
        @if ($vetHorses->count() === 1)
            <a href="{{ route('horses.vet-summary', $vetHorses->first()) }}" class="btn-secondary" data-vet-share><x-icon name="share" class="size-4" /> Partager avec le vétérinaire</a>
        @elseif ($vetHorses->count() > 1)
            <div x-data="{ open: false }" @keydown.escape.window="open = false">
                <button type="button" class="btn-secondary" @click="open = true" data-vet-share><x-icon name="share" class="size-4" /> Partager avec le vétérinaire</button>
                {{-- Plusieurs chevaux : on choisit lequel avant d'ouvrir son résumé de santé (fenêtre rattachée au corps de la page, au-dessus de tout). --}}
                <template x-teleport="body">
                <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[70] flex items-end justify-center bg-slate-900/45 sm:items-center" @click.self="open = false" role="dialog" aria-modal="true" aria-labelledby="vet-share-title" data-vet-share-picker>
                    <div class="w-full max-w-md rounded-t-3xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-xl sm:rounded-3xl">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 id="vet-share-title" class="text-lg font-semibold">Quel cheval ?</h2>
                            <button type="button" class="btn-ghost px-2" @click="open = false" aria-label="Fermer"><x-icon name="x" /></button>
                        </div>
                        <p class="mb-3 text-sm text-slate-500">Son résumé de santé s'affiche ; vous pourrez ensuite l'envoyer par e-mail ou SMS au vétérinaire, avec un lien temporaire.</p>
                        <div class="max-h-[60dvh] space-y-1 overflow-y-auto">
                            @foreach ($vetHorses as $h)
                                <a href="{{ route('horses.vet-summary', $h) }}" class="flex min-h-14 items-center gap-3 rounded-2xl px-3 py-2 hover:bg-slate-50">
                                    @if ($h->mainPhotoUrl())<img src="{{ $h->mainPhotoUrl() }}" alt="" loading="lazy" class="size-10 rounded-xl object-cover">@else<span class="flex size-10 items-center justify-center rounded-xl bg-brand-100 font-bold text-brand-800">{{ mb_substr($h->shortName(), 0, 1) }}</span>@endif
                                    <span class="flex-1 font-medium">{{ $h->shortName() }}</span>
                                    <x-icon name="chevron" class="size-5 text-slate-300" />
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                </template>
            </div>
        @endif
    </x-page-header>
    <div class="grid gap-4 lg:grid-cols-2">
        <x-section title="Échéances (60 jours)">
            @forelse ($due as $c)
                <a href="{{ route('horses.care.show', [$c->horse_id, $c]) }}" class="flex items-center justify-between border-t border-slate-100 py-2 text-sm first:border-0">
                    <span><span class="font-medium">{{ $c->horse->shortName() }}</span> — {{ $c->category->name }}<span class="block text-xs text-slate-500">{{ $c->professional?->fullName() }}</span></span>
                    <x-badge :color="$c->next_check_on->isPast() ? 'red' : (today()->diffInDays($c->next_check_on) < 14 ? 'amber' : 'slate')">{{ $c->next_check_on->format('d/m/Y') }}</x-badge>
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
