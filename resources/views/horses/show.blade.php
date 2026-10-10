@php $can = fn ($p) => in_array($p, $perms, true); @endphp
<x-layouts.app :title="$horse->shortName()">
    <div class="mb-4 flex flex-wrap items-start gap-4">
        @if ($horse->mainPhotoUrl())<img src="{{ $horse->mainPhotoUrl() }}" alt="Photo de {{ $horse->shortName() }}" class="size-20 rounded-2xl object-cover sm:size-24">@else<div class="flex size-20 items-center justify-center rounded-2xl bg-brand-100 text-3xl font-bold text-brand-800 sm:size-24">{{ mb_substr($horse->shortName(), 0, 1) }}</div>@endif
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold">{{ $horse->shortName() }}</h1>
            @if ($horse->usual_name)<p class="text-sm text-slate-500">{{ $horse->official_name }}</p>@endif
            <p class="text-sm text-slate-600">{{ collect([$horse->breed?->name, \App\Models\Horse::SEXES[$horse->sex] ?? null, $horse->age() !== null ? $horse->age().' ans' : null, $horse->coat])->filter()->implode(' · ') }}</p>
            <div class="mt-1 flex flex-wrap gap-1">
                <x-badge>{{ $horse->organization->name }}</x-badge>
                @if ($horse->isArchived())<x-badge color="amber">Archivé — lecture seule</x-badge>@endif
                @if ($isOffline)<x-badge color="brand">Disponible hors ligne</x-badge>@endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($can('horse.edit') && ! $horse->isArchived())<a href="{{ route('horses.edit', $horse) }}" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Modifier</a>@endif
            <form method="POST" action="{{ route('horses.offline', $horse) }}">@csrf<button class="btn-secondary" title="Rendre disponible dans le mode écurie hors ligne"><x-icon name="offline" class="size-4" /> {{ $isOffline ? 'Retirer du hors ligne' : 'Disponible hors ligne' }}</button></form>
            <a href="{{ route('horses.export.pdf', $horse) }}" class="btn-ghost"><x-icon name="download" class="size-4" /> PDF</a>
        </div>
    </div>

    @include('horses._tabs')

    @if ($sources->isNotEmpty())
        <x-section title="Informations importées à vérifier" class="mb-4 border-amber-200 bg-amber-50/40">
            @foreach ($sources as $src)
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-amber-100 py-2 text-sm first:border-0">
                    <span><strong>{{ \App\Services\HorseSearch\HorseCandidate::FIELDS[$src->field] ?? $src->field }}</strong> : {{ $src->value }} <span class="text-xs text-slate-500">— {{ $src->source }} ({{ $src->fetched_at->format('d/m/Y') }})</span></span>
                    <span class="flex gap-1">
                        <form method="POST" action="{{ route('horses.sources.decide', [$horse, $src, 'confirm']) }}">@csrf<button class="btn-secondary text-xs">Confirmer</button></form>
                        <form method="POST" action="{{ route('horses.sources.decide', [$horse, $src, 'reject']) }}">@csrf<button class="btn-ghost text-xs">Rejeter</button></form>
                    </span>
                </div>
            @endforeach
        </x-section>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($horse->precautions || $horse->care_instructions)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
                    @if ($horse->precautions)<p class="font-semibold text-amber-900">Précautions</p><p class="mb-2 whitespace-pre-line">{{ $horse->precautions }}</p>@endif
                    @if ($horse->care_instructions)<p class="font-semibold text-amber-900">Consignes de soins</p><p class="whitespace-pre-line">{{ $horse->care_instructions }}</p>@endif
                </div>
            @endif
            <x-section title="Identité">
                <x-dl :items="[
                    'Nom officiel' => $horse->official_name,
                    'N° SIRE' => $horse->identifier('sire'),
                    'UELN' => $horse->identifier('ueln'),
                    'Transpondeur' => $horse->identifier('transponder'),
                    'Date de naissance' => $horse->birth_date?->format('d/m/Y') ?? $horse->birth_year,
                    'Taille' => $horse->height_cm ? $horse->height_cm.' cm' : null,
                    'Pays de naissance' => $horse->birth_country,
                    'Éleveur' => $horse->breeder,
                    'Discipline' => \App\Models\Horse::DISCIPLINES[$horse->main_discipline] ?? null,
                    'Niveau' => $horse->work_level,
                    'Lieu de vie' => $horse->current_location,
                    'Origine' => $horse->origin?->studbook,
                ]" />
                @if ($horse->particularities)<div class="mt-4"><p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Particularités</p><p class="text-sm whitespace-pre-line">{{ $horse->particularities }}</p></div>@endif
                @if ($horse->general_notes)<div class="mt-4"><p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Observations générales</p><p class="text-sm whitespace-pre-line">{{ $horse->general_notes }}</p></div>@endif
            </x-section>

            @if ($can('sessions.view'))
            <x-section title="Dernières séances" :action="route('sessions.index', ['horse' => $horse->id])">
                @if ($can('sessions.create'))
                <x-slot:headerActions><a href="{{ route('sessions.create', ['horse' => $horse->id]) }}" class="btn-primary text-sm"><x-icon name="plus" class="size-4" /> Séance</a></x-slot:headerActions>
                @endif
                @forelse ($sessions as $s)
                    <a href="{{ route('sessions.show', $s) }}" class="flex justify-between border-t border-slate-100 py-2 text-sm first:border-0"><span>{{ $s->scheduled_at->format('d/m/Y H:i') }} · {{ $s->typeLabel() }}<span class="block text-xs text-slate-500">{{ $s->objective }} · {{ $s->riderLabel() }}</span></span><x-badge :color="$s->status === 'completed' ? 'green' : 'slate'">{{ \App\Models\RidingSession::STATUSES[$s->status] }}</x-badge></a>
                @empty <p class="text-sm text-slate-500">Aucune séance.</p> @endforelse
            </x-section>
            @endif

            @if ($can('health.view'))
            <x-section title="Derniers soins" :action="route('horses.care.index', $horse)">
                @forelse ($care as $c)
                    <a href="{{ route('horses.care.show', [$horse, $c]) }}" class="flex justify-between border-t border-slate-100 py-2 text-sm first:border-0"><span><span class="font-medium">{{ $c->category->name }}</span> {{ $c->reason ? '— '.$c->reason : '' }}<span class="block text-xs text-slate-500">{{ $c->professional?->fullName() }}</span></span><span class="text-xs text-slate-500">{{ $c->performed_at->format('d/m/Y') }}</span></a>
                @empty <p class="text-sm text-slate-500">Aucun soin enregistré.</p> @endforelse
            </x-section>
            @endif

            <x-section title="Photos">
                @if ($horse->photos->isEmpty())<p class="text-sm text-slate-500">Aucune photo.</p>@endif
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    @foreach ($horse->photos as $photo)
                        <div class="group relative">
                            <a href="{{ route('horses.photos.show', [$horse, $photo]) }}" target="_blank"><img src="{{ route('horses.photos.show', [$horse, $photo]) }}?thumb=1" alt="{{ $photo->caption }}" loading="lazy" class="aspect-square w-full rounded-lg object-cover"></a>
                            @if ($can('horse.edit'))
                                <div class="mt-1 flex gap-1 text-xs">
                                    @if ($horse->main_photo_path !== (string) $photo->id)<form method="POST" action="{{ route('horses.photos.main', [$horse, $photo]) }}">@csrf<button class="link">Principale</button></form>@endif
                                    <x-confirm-delete :action="route('horses.photos.destroy', [$horse, $photo])" class="text-red-600" message="Supprimer cette photo ?">Suppr.</x-confirm-delete>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($can('horse.edit'))
                    <form method="POST" action="{{ route('horses.photos.store', $horse) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="file" name="photo" accept="image/*" capture="environment" required class="text-sm" aria-label="Photo">
                        <input name="caption" placeholder="Légende" class="input max-w-48 py-2 text-sm" aria-label="Légende">
                        <button class="btn-secondary">Ajouter</button>
                    </form>
                @endif
            </x-section>
        </div>

        <div class="space-y-4">
            @if ($can('comments.create'))
            <x-section title="Signaler une observation">
                <form method="POST" action="{{ route('horses.observations.store', $horse) }}" class="space-y-2">
                    @csrf
                    <x-textarea name="body" rows="2" placeholder="Boiterie, plaie, comportement inhabituel…" required aria-label="Observation" />
                    <div class="flex gap-2"><x-select name="severity" :options="\App\Models\HealthObservation::SEVERITIES" value="info" class="flex-1" aria-label="Niveau" /><button class="btn-primary">Envoyer</button></div>
                </form>
                @foreach ($observations as $o)
                    <div class="mt-3 border-t border-slate-100 pt-2 text-sm"><x-badge :color="['info' => 'slate', 'watch' => 'amber', 'alert' => 'red'][$o->severity]">{{ \App\Models\HealthObservation::SEVERITIES[$o->severity] }}</x-badge> {{ $o->body }}<p class="text-xs text-slate-500">{{ $o->author?->name }} · {{ $o->observed_at->diffForHumans() }}</p></div>
                @endforeach
            </x-section>
            @endif

            @if ($treatments->isNotEmpty())
            <x-section title="Traitements en cours">
                @foreach ($treatments as $t)<p class="border-t border-slate-100 py-2 text-sm first:border-0"><span class="font-medium">{{ $t->product }}</span><span class="block text-xs text-slate-500">{{ $t->dosage }} · {{ $t->frequency }} {{ $t->ends_on ? '· fin '.$t->ends_on->format('d/m') : '' }}</span></p>@endforeach
            </x-section>
            @endif

            @if ($events->isNotEmpty())
            <x-section title="À venir" :action="route('calendar.index', ['horse' => $horse->id])">
                @foreach ($events as $e)<a href="{{ route('calendar.show', $e) }}" class="block border-t border-slate-100 py-2 text-sm first:border-0"><span class="font-medium">{{ $e->starts_at->format('d/m H:i') }}</span> {{ $e->title }}</a>@endforeach
            </x-section>
            @endif

            <x-section title="Propriétaires et détenteurs">
                @foreach ($horse->ownerships->sortBy('ended_on') as $o)
                    <div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm first:border-0 {{ $o->ended_on && $o->ended_on->isPast() ? 'opacity-60' : '' }}">
                        <span>{{ $o->displayName() }} <span class="text-xs text-slate-500">({{ ['owner' => 'propriétaire', 'co_owner' => 'copropriétaire', 'keeper' => 'détenteur'][$o->role] }}{{ $o->share_percent ? ', '.rtrim(rtrim($o->share_percent, '0'), '.').' %' : '' }})</span>
                            <span class="block text-xs text-slate-500">depuis {{ $o->started_on?->format('d/m/Y') ?? '—' }}{{ $o->ended_on ? ' · jusqu\'au '.$o->ended_on->format('d/m/Y') : '' }}</span></span>
                        @if ($can('horse.manage') && ! $o->ended_on)<x-confirm-delete :action="route('horses.ownerships.end', [$horse, $o])" method="POST" message="Enregistrer la fin de propriété ?" class="text-xs link">Terminer</x-confirm-delete>@endif
                    </div>
                @endforeach
                @if ($can('horse.manage'))
                    <details class="mt-2 text-sm"><summary class="cursor-pointer link">Ajouter un propriétaire / détenteur</summary>
                        <form method="POST" action="{{ route('horses.ownerships.store', $horse) }}" class="mt-2 space-y-2">
                            @csrf
                            <x-field name="email" type="email" label="Email d'un compte existant" />
                            <x-field name="owner_name" label="ou nom (sans compte)" />
                            <x-select name="role" label="Rôle" :options="['owner' => 'Propriétaire', 'co_owner' => 'Copropriétaire', 'keeper' => 'Détenteur']" value="co_owner" />
                            <x-field name="share_percent" type="number" step="0.01" label="Part (%)" />
                            <button class="btn-secondary w-full">Ajouter</button>
                        </form>
                    </details>
                @endif
            </x-section>

            @if ($horse->assignments->isNotEmpty() || $horse->locations->isNotEmpty())
            <x-section title="Lieux et prise en charge">
                @foreach ($horse->assignments as $a)<p class="text-sm"><x-badge :color="$a->status === 'active' ? 'green' : 'amber'">{{ $a->status === 'active' ? 'Actif' : 'En attente' }}</x-badge> {{ \App\Models\HorseOrganizationAssignment::KINDS[$a->kind] }} — {{ $a->organization->name }}</p>@endforeach
                @foreach ($horse->locations->take(5) as $l)<p class="mt-1 text-xs text-slate-500">{{ $l->label }} : {{ $l->started_on->format('d/m/Y') }} → {{ $l->ended_on?->format('d/m/Y') ?? 'aujourd\'hui' }}</p>@endforeach
            </x-section>
            @endif

            @if ($can('horse.manage'))
            <x-section title="Gestion du dossier">
                <div class="space-y-3 text-sm">
                    @if ($transferTargets->isNotEmpty())
                        <form method="POST" action="{{ route('horses.transfer', $horse) }}" class="space-y-2" onsubmit="return confirm('Transférer la gestion de ce cheval ? L\'historique est conservé.')">
                            @csrf
                            <x-select name="organization_id" label="Transférer la gestion vers" :options="$transferTargets->pluck('name', 'id')" />
                            <button class="btn-secondary w-full">Transférer</button>
                        </form>
                    @endif
                    <a href="{{ route('horses.history', $horse) }}" class="btn-ghost w-full">Historique des accès et modifications</a>
                    @if ($horse->isArchived())
                        <form method="POST" action="{{ route('horses.unarchive', $horse) }}">@csrf<button class="btn-secondary w-full">Restaurer</button></form>
                    @else
                        <form method="POST" action="{{ route('horses.archive', $horse) }}" onsubmit="return confirm('Archiver ce cheval ? Son dossier sera conservé en lecture seule.')">@csrf<button class="btn-secondary w-full">Archiver (départ, décès, vente…)</button></form>
                    @endif
                    <details><summary class="cursor-pointer text-red-600">Supprimer le dossier</summary>
                        <form method="POST" action="{{ route('horses.destroy', $horse) }}" class="mt-2 space-y-2">
                            @csrf @method('DELETE')
                            <p class="text-xs text-slate-600">Le dossier sera supprimé pour tous les utilisateurs, puis définitivement effacé après 30 jours. Préférez l'archivage pour conserver l'historique.</p>
                            <x-field name="confirm_name" :label="'Tapez « '.$horse->official_name.' » pour confirmer'" />
                            <button class="btn-danger w-full">Supprimer</button>
                        </form>
                    </details>
                </div>
            </x-section>
            @endif
        </div>
    </div>
</x-layouts.app>
