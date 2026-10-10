<x-layouts.app :title="'Santé · '.$horse->shortName()">
    <x-page-header :title="$horse->shortName()" subtitle="Santé, soins et traitements" :back="route('horses.show', $horse)">
        @if ($canHealth)<a href="{{ route('horses.export.health', $horse) }}" class="btn-ghost"><x-icon name="download" class="size-4" /> Export PDF</a>@endif
        @if ($canEdit)<a href="{{ route('horses.care.create', $horse) }}" class="btn-primary"><x-icon name="plus" /> Soin</a>@endif
    </x-page-header>
    @include('horses._tabs')
    <p class="mb-4 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600">Cet espace est un carnet de suivi : il ne remplace pas l'avis d'un vétérinaire et ne produit aucun diagnostic ni prescription.</p>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($canHealth)
            <x-section title="Historique des soins">
                <form method="GET" class="mb-3"><x-select name="category" :options="$categories" :value="request('category')" placeholder="Toutes les catégories" onchange="this.form.submit()" aria-label="Catégorie" /></form>
                @forelse ($records as $r)
                    <a href="{{ route('horses.care.show', [$horse, $r]) }}" class="flex items-start justify-between gap-3 border-t border-slate-100 py-3 text-sm first:border-0 hover:bg-slate-50">
                        <span class="min-w-0"><x-badge color="brand">{{ $r->category->name }}</x-badge> <span class="font-medium">{{ $r->reason }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $r->professional?->fullName() }} {{ $r->care_performed ? '· '.\Illuminate\Support\Str::limit($r->care_performed, 90) : '' }}</span>
                            @if ($r->next_check_on)<span class="block text-xs {{ $r->next_check_on->isPast() ? 'text-red-600' : 'text-amber-700' }}">Prochain contrôle : {{ $r->next_check_on->format('d/m/Y') }}</span>@endif</span>
                        <span class="shrink-0 text-right text-xs text-slate-500">{{ $r->performed_at->format('d/m/Y') }}@if ($r->cost)<br>{{ number_format((float) $r->cost, 2, ',', ' ') }} €@endif</span>
                    </a>
                @empty <p class="text-sm text-slate-500">Aucun soin enregistré.</p> @endforelse
                {{ $records?->links() }}
            </x-section>
            @endif

            <x-section title="Traitements">
                @forelse ($treatments as $t)
                    <div class="border-t border-slate-100 py-3 text-sm first:border-0" x-data="{ edit: false }">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div><span class="font-semibold">{{ $t->product }}</span> <x-badge :color="$t->status === 'active' ? 'green' : 'slate'">{{ \App\Models\Treatment::STATUSES[$t->status] }}</x-badge>
                                <p class="text-slate-600">{{ collect([$t->dosage, $t->frequency])->filter()->implode(' · ') }}</p>
                                <p class="text-xs text-slate-500">Du {{ $t->starts_on->format('d/m/Y') }} {{ $t->ends_on ? 'au '.$t->ends_on->format('d/m/Y') : '' }} {{ $t->responsible ? '· responsable : '.$t->responsible->name : '' }}</p>
                                @if ($t->instructions)<p class="mt-1 whitespace-pre-line">{{ $t->instructions }}</p>@endif
                            </div>
                            <div class="flex gap-1">
                                @if ($t->track_administrations && $t->status === 'active' && ($canEdit || $t->responsible_user_id === auth()->id()))
                                    <form method="POST" action="{{ route('horses.treatments.administer', [$horse, $t]) }}">@csrf<button class="btn-secondary text-xs"><x-icon name="check" class="size-4" /> Administré</button></form>
                                @endif
                                @if ($canEdit)<button @click="edit = ! edit" class="btn-ghost text-xs">Modifier</button>@endif
                            </div>
                        </div>
                        @if ($t->track_administrations && $t->administrations->isNotEmpty())
                            <p class="mt-1 text-xs text-slate-500">Dernières administrations : {{ $t->administrations->take(5)->map(fn ($a) => $a->administered_at->format('d/m H:i').' ('.($a->author?->name ?? '—').')')->implode(', ') }}</p>
                        @endif
                        @if ($canEdit)
                        <form x-show="edit" x-cloak method="POST" action="{{ route('horses.treatments.update', [$horse, $t]) }}" class="mt-2 grid gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-2">
                            @csrf @method('PUT')
                            @include('health._treatment-fields', ['t' => $t])
                            <x-select name="status" label="Statut" :options="\App\Models\Treatment::STATUSES" :value="$t->status" />
                            <button class="btn-primary sm:col-span-2">Enregistrer</button>
                        </form>
                        @endif
                    </div>
                @empty <p class="text-sm text-slate-500">Aucun traitement.</p> @endforelse
                @if ($canEdit)
                <details class="mt-3"><summary class="cursor-pointer text-sm link">Ajouter un traitement prescrit</summary>
                    <form method="POST" action="{{ route('horses.treatments.store', $horse) }}" class="mt-2 grid gap-2 sm:grid-cols-2">
                        @csrf
                        @include('health._treatment-fields', ['t' => new \App\Models\Treatment(['starts_on' => now()])])
                        <button class="btn-primary sm:col-span-2">Ajouter</button>
                    </form>
                </details>
                @endif
            </x-section>
        </div>

        <x-section title="Observations">
            @if ($canObserve)
            <form method="POST" action="{{ route('horses.observations.store', $horse) }}" class="mb-3 space-y-2">
                @csrf
                <x-textarea name="body" rows="2" required aria-label="Observation" placeholder="Décrire ce qui a été constaté" />
                <div class="flex gap-2"><x-select name="severity" :options="\App\Models\HealthObservation::SEVERITIES" value="info" class="flex-1" aria-label="Niveau" /><button class="btn-primary">Ajouter</button></div>
            </form>
            @endif
            @forelse ($observations as $o)
                <div class="border-t border-slate-100 py-2 text-sm {{ $o->resolved_at ? 'opacity-60' : '' }}">
                    <x-badge :color="['info' => 'slate', 'watch' => 'amber', 'alert' => 'red'][$o->severity]">{{ \App\Models\HealthObservation::SEVERITIES[$o->severity] }}</x-badge>
                    <p class="whitespace-pre-line">{{ $o->body }}</p>
                    <p class="text-xs text-slate-500">{{ $o->author?->name }} · {{ $o->observed_at->format('d/m/Y H:i') }} {{ $o->resolved_at ? '· traité' : '' }}</p>
                    @if (! $o->resolved_at && $canEdit)<form method="POST" action="{{ route('horses.observations.resolve', [$horse, $o]) }}">@csrf<button class="text-xs link">Marquer traité</button></form>@endif
                </div>
            @empty <p class="text-sm text-slate-500">Aucune observation.</p> @endforelse
        </x-section>
    </div>
</x-layouts.app>
