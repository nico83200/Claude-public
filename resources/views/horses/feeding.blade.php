@php $qty = fn ($e) => $e->quantity !== null ? rtrim(rtrim((string) $e->quantity, '0'), '.').' '.$e->unit : ''; @endphp
<x-layouts.app :title="'Alimentation · '.$horse->shortName()">
    <x-horse-header :horse="$horse">
        @if ($canEdit)<button type="button" class="btn-primary" x-data @click="$dispatch('feeding-open', 'new')" data-new-ration><x-icon name="plus" class="size-4" /> Ration</button>@endif
    </x-horse-header>

    <div x-data="{ open: @js($canEdit && $rations->isEmpty() ? 'new' : null) }" @feeding-open.window="open = $event.detail; $nextTick(() => document.getElementById('ration-form-' + $event.detail)?.scrollIntoView({ behavior: 'smooth', block: 'start' }))" @feeding-close="open = null">
        @if ($canEdit)
            <x-section title="Nouvelle ration" id="ration-form-new" class="mb-4" x-show="open === 'new'" x-cloak>
                <p class="-mt-1 mb-4 text-sm text-slate-500">Plusieurs rations peuvent être suivies en même temps : ration du matin, du soir, complément sur quelques jours…</p>
                @include('horses._feeding-form', ['plan' => null])
            </x-section>
        @endif

        @if ($rations->isEmpty() && ! $canEdit)
            <x-empty title="Aucune ration" icon="food">La ration du cheval n'a pas encore été renseignée.</x-empty>
        @endif

        <div class="grid gap-4 lg:grid-cols-2" data-rations>
            @foreach ($rations as $plan)
                <x-section :title="$plan->name" data-ration="{{ $plan->id }}">
                    @if ($canEdit)
                        <x-slot:headerActions>
                            <button type="button" class="btn-ghost size-11 px-0" @click="open = open === {{ $plan->id }} ? null : {{ $plan->id }}" aria-label="Modifier la ration" title="Modifier"><x-icon name="pencil" class="size-5" /></button>
                        </x-slot:headerActions>
                    @endif
                    <p class="-mt-1 mb-3 text-xs text-slate-500">
                        @if ($plan->isUpcoming())<x-badge color="blue">À partir du {{ $plan->starts_on->format('d/m/Y') }}</x-badge>@else Depuis le {{ $plan->starts_on->translatedFormat('j F Y') }}@endif
                        @if ($plan->ends_on) · jusqu'au {{ $plan->ends_on->translatedFormat('j F Y') }} ({{ $plan->ends_on->isToday() ? 'dernier jour' : 'encore '.(today()->diffInDays($plan->ends_on) + 1).' j' }})@endif
                        · {{ $plan->author?->name ?? '—' }}
                    </p>
                    <div x-show="open !== {{ $plan->id }}">
                        <ul class="divide-y divide-slate-100">
                            @forelse ($plan->entries as $e)
                                <li class="flex items-start gap-3 py-3">
                                    <span class="w-12 shrink-0 text-sm font-semibold text-slate-900 tabular-nums">{{ $e->time_of_day ? substr($e->time_of_day, 0, 5) : '—' }}</span>
                                    <span class="min-w-0 flex-1"><span class="font-medium">{{ $e->feed }}</span> @if ($e->is_supplement)<x-badge color="purple">Complément</x-badge>@endif
                                        @if ($e->frequency || $e->note)<span class="block text-xs text-slate-500">{{ collect([$e->frequency, $e->note])->filter()->implode(' · ') }}</span>@endif</span>
                                    <span class="shrink-0 text-sm font-semibold whitespace-nowrap">{{ $qty($e) }}</span>
                                </li>
                            @empty
                                <li class="py-2 text-sm text-slate-500">Aucun aliment noté.</li>
                            @endforelse
                        </ul>
                        @if ($plan->instructions)<p class="mt-3 rounded-2xl bg-brand-50 p-3 text-sm whitespace-pre-line text-brand-900">{{ $plan->instructions }}</p>@endif
                        @if ($canEdit)
                            <div class="mt-3 flex flex-wrap justify-end gap-1 border-t border-slate-100 pt-3">
                                <form method="POST" action="{{ route('horses.feeding.archive', [$horse, $plan]) }}" data-confirm="Archiver la ration « {{ $plan->name }} » ? Elle s'arrête aujourd'hui et reste dans l'historique." data-confirm-ok="Archiver" data-confirm-tone="neutral">@csrf<button class="btn-ghost min-h-10 text-sm"><x-icon name="archive" class="size-4" /> Archiver</button></form>
                                <form method="POST" action="{{ route('horses.feeding.destroy', [$horse, $plan]) }}" data-confirm="Supprimer définitivement la ration « {{ $plan->name }} » ? À utiliser en cas d'erreur de saisie." data-confirm-ok="Supprimer">@csrf @method('DELETE')<button class="btn-ghost min-h-10 text-sm text-red-600"><x-icon name="trash" class="size-4" /> Supprimer</button></form>
                            </div>
                        @endif
                    </div>
                    @if ($canEdit)
                        <div id="ration-form-{{ $plan->id }}" x-show="open === {{ $plan->id }}" x-cloak>@include('horses._feeding-form', ['plan' => $plan])</div>
                    @endif
                </x-section>
            @endforeach
        </div>

        @if ($archived->isNotEmpty())
            <x-section title="Rations archivées" class="mt-4" data-archived-rations>
                @foreach ($archived as $p)
                    <details class="border-t border-slate-100 py-2 text-sm first:border-0">
                        <summary class="cursor-pointer"><span class="font-medium">{{ $p->name }}</span> — du {{ $p->starts_on->format('d/m/Y') }}{{ $p->ends_on ? ' au '.$p->ends_on->format('d/m/Y') : '' }} · {{ $p->author?->name }}</summary>
                        <ul class="mt-1 list-disc pl-6 text-slate-600">@foreach ($p->entries as $e)<li>{{ $e->time_of_day ? substr($e->time_of_day, 0, 5).' — ' : '' }}{{ $e->feed }} {{ $qty($e) }}</li>@endforeach</ul>
                        @if ($canEdit)
                            <div class="mt-2 flex flex-wrap gap-1">
                                <form method="POST" action="{{ route('horses.feeding.archive', [$horse, $p]) }}">@csrf<input type="hidden" name="restore" value="1"><button class="btn-ghost min-h-10 text-sm"><x-icon name="refresh" class="size-4" /> Reprendre</button></form>
                                <form method="POST" action="{{ route('horses.feeding.destroy', [$horse, $p]) }}" data-confirm="Supprimer définitivement la ration « {{ $p->name }} » ?" data-confirm-ok="Supprimer">@csrf @method('DELETE')<button class="btn-ghost min-h-10 text-sm text-red-600"><x-icon name="trash" class="size-4" /> Supprimer</button></form>
                            </div>
                        @endif
                    </details>
                @endforeach
            </x-section>
        @endif
    </div>
</x-layouts.app>
