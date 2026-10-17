@php
    $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €';
    $active = $contracts->filter(fn ($c) => ! $c->ends_on || $c->ends_on->isFuture() || $c->ends_on->isToday());
    $prev = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $period.'-01')->subMonth()->format('Y-m');
    $next = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $period.'-01')->addMonth()->format('Y-m');
@endphp
<x-layouts.app title="Pension">
    <x-page-header title="Pension" :subtitle="$org->name">
        <button type="button" class="btn-secondary" onclick="document.getElementById('new-charge').showModal()"><x-icon name="plus" class="size-4" /> Prestation</button>
        <button type="button" class="btn-primary" onclick="document.getElementById('new-contract').showModal()"><x-icon name="plus" class="size-4" /> Pensionnaire</button>
    </x-page-header>

    <div class="card mb-5 grid grid-cols-3 divide-x divide-slate-100 py-4 text-center">
        <div class="px-2"><p class="text-xs text-slate-500">Pensionnaires</p><p class="mt-1 text-xl font-bold sm:text-2xl">{{ $active->count() }}</p></div>
        <div class="px-2"><p class="text-xs text-slate-500">Pensions / mois</p><p class="mt-1 text-xl font-bold sm:text-2xl">{{ $eur($active->sum('monthly_amount')) }}</p></div>
        <div class="px-2"><p class="text-xs text-slate-500">En attente de paiement</p><p class="mt-1 text-xl font-bold sm:text-2xl {{ $unpaid > 0 ? 'text-amber-700' : '' }}">{{ $eur($unpaid) }}</p></div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Relevés du mois --}}
        <x-section class="lg:col-span-2">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-1">
                    <a href="{{ route('boarding.index', ['mois' => $prev]) }}" class="btn-icon size-9" aria-label="Mois précédent"><x-icon name="back" class="size-4" /></a>
                    <h2 class="px-2 text-[17px] font-semibold">Relevés · {{ $periodLabel }}</h2>
                    <a href="{{ route('boarding.index', ['mois' => $next]) }}" class="btn-icon size-9" aria-label="Mois suivant"><x-icon name="chevron" class="size-4" /></a>
                </div>
                <form method="POST" action="{{ route('boarding.generate') }}">@csrf<input type="hidden" name="period" value="{{ $period }}"><button class="btn-primary min-h-10 px-4 text-sm"><x-icon name="refresh" class="size-4" /> {{ $statements->isEmpty() ? 'Préparer les relevés' : 'Recalculer les brouillons' }}</button></form>
            </div>
            @forelse ($statements as $s)
                <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 py-3 first:border-0" data-statement="{{ $s->number }}">
                    <span class="min-w-0 flex-1"><a href="{{ route('boarding.statement', $s) }}" class="font-medium text-slate-900 hover:underline">{{ $s->owner_name }}</a>
                        <span class="block text-xs text-slate-500">{{ $s->title() }} · N° {{ $s->number }} · {{ count($s->lines) }} ligne(s) · {{ collect($s->lines)->pluck('horse')->unique()->implode(', ') }}</span></span>
                    <span class="font-semibold tabular-nums">{{ $eur($s->total) }}</span>
                    <x-badge :color="['draft' => 'slate', 'sent' => 'amber', 'paid' => 'green'][$s->status]">{{ \App\Models\BoardingStatement::STATUSES[$s->status] }}</x-badge>
                    <span class="flex gap-1">
                        <a href="{{ route('boarding.statement', [$s, 'format' => 'pdf']) }}" class="btn-ghost min-h-9 px-3 text-sm">PDF</a>
                        @if ($s->status === 'draft')
                            <form method="POST" action="{{ route('boarding.send', $s) }}">@csrf<button class="btn-secondary min-h-9 px-3 text-sm"><x-icon name="send" class="size-4" /> Envoyer</button></form>
                        @elseif ($s->status === 'sent')
                            <form method="POST" action="{{ route('boarding.pay', $s) }}">@csrf<button class="btn-secondary min-h-9 px-3 text-sm"><x-icon name="check" class="size-4" /> Payé</button></form>
                        @endif
                    </span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucun relevé pour {{ mb_strtolower($periodLabel) }}. « Préparer les relevés » calcule un relevé par propriétaire : pension au prorata des jours de présence et prestations du mois, avec les cours donnés par les enseignants de l'écurie (un relevé de cours pour les cavaliers sans pension).</p>
            @endforelse
        </x-section>

        {{-- Pensionnaires --}}
        <x-section title="Pensionnaires">
            @forelse ($contracts as $c)
                <div class="border-t border-slate-100 py-3 text-sm first:border-0" x-data="{ end: false }">
                    <div class="flex items-start justify-between gap-2">
                        <span class="min-w-0"><span class="font-medium text-slate-900">{{ $c->horse->shortName() }}</span> · {{ $c->label }}
                            <span class="block text-xs text-slate-500">{{ $c->owner_name }}@if ($c->owner_user_id) <x-icon name="check" class="inline size-3.5 text-brand-600" /> compte Jack&Cie @endif · depuis le {{ $c->starts_on->format('d/m/Y') }}@if ($c->ends_on) · jusqu'au {{ $c->ends_on->format('d/m/Y') }}@endif</span></span>
                        <span class="shrink-0 font-semibold tabular-nums">{{ $eur($c->monthly_amount) }}</span>
                    </div>
                    @unless ($c->ends_on)
                        <button type="button" class="mt-1 text-xs link" @click="end = ! end">Fin de pension…</button>
                        <form x-show="end" x-cloak method="POST" action="{{ route('boarding.contracts.end', $c) }}" class="mt-2 flex items-end gap-2">@csrf<x-field name="ends_on" type="date" label="Dernier jour" :value="now()->toDateString()" required class="flex-1" /><button class="btn-secondary min-h-11">Enregistrer</button></form>
                    @endunless
                </div>
            @empty
                <p class="text-sm text-slate-500">Ajoutez les chevaux en pension et le montant mensuel facturé à leur propriétaire.</p>
            @endforelse
            @if ($withoutContract->isNotEmpty())<p class="mt-3 text-xs text-amber-700">Sans pension enregistrée : {{ $withoutContract->map->shortName()->implode(', ') }}.</p>@endif
        </x-section>

        {{-- Prestations --}}
        <x-section :title="'Prestations · '.$periodLabel">
            @forelse ($charges as $ch)
                <div class="flex items-center justify-between gap-2 border-t border-slate-100 py-2.5 text-sm first:border-0">
                    <span class="min-w-0"><span class="font-medium text-slate-900">{{ $ch->label }}</span> · {{ $ch->horse->shortName() }}<span class="block text-xs text-slate-500">{{ $ch->charged_on->format('d/m') }}@if ((float) $ch->quantity != 1) · {{ rtrim(rtrim(number_format((float) $ch->quantity, 2, ',', ''), '0'), ',') }} × {{ $eur($ch->unit_price) }}@endif @if ($ch->statement_id) · facturée @endif</span></span>
                    <span class="flex items-center gap-2"><span class="tabular-nums">{{ $eur($ch->total()) }}</span>@unless ($ch->statement_id)<x-confirm-delete :action="route('boarding.charges.destroy', $ch)" message="Supprimer cette prestation ?" />@endunless</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Ferrure avancée, sortie au paddock, complément alimentaire… : notez ici ce qui s'ajoute à la pension.</p>
            @endforelse
        </x-section>

        {{-- Cours donnés par les enseignants de l'écurie : repris sur le relevé de la personne qui paie. --}}
        <x-section :title="'Cours · '.$periodLabel" data-stable-lessons>
            @forelse ($lessons as $l)
                <div class="flex items-center justify-between gap-2 border-t border-slate-100 py-2.5 text-sm first:border-0" data-stable-lesson="{{ $l->id }}">
                    <span class="min-w-0"><span class="font-medium text-slate-900">{{ $l->label }}</span> · {{ $l->horse?->shortName() }}<span class="block text-xs text-slate-500">{{ $l->charged_on->format('d/m') }} · {{ $l->payer?->name }}{{ $l->boarding_statement_id ? ' · facturé' : '' }}{{ $l->paid_on ? ' · payé' : '' }}</span></span>
                    <span class="shrink-0 whitespace-nowrap tabular-nums">{{ $eur($l->amount) }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Les cours notés avec les enseignants de l'écurie (et leurs tarifs, dans Intervenants) apparaissent ici et sur le relevé de la personne qui les paie.</p>
            @endforelse
        </x-section>
    </div>

    {{-- Nouveau pensionnaire --}}
    <dialog id="new-contract" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl p-0 backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('boarding.contracts.store') }}" class="space-y-3 p-5" x-data="{ s: @js($suggestions), fill(id) { const o = this.s[id]; if (! o) return; this.$refs.n.value = o.name || ''; this.$refs.e.value = o.email || ''; } }">
            @csrf
            <h2 class="text-[17px] font-semibold">Nouveau pensionnaire</h2>
            <x-select name="horse_id" label="Cheval" :options="$horses->mapWithKeys(fn ($h) => [$h->id => $h->shortName()])" placeholder="Choisir…" required @change="fill($event.target.value)" />
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label" for="bc_owner">Propriétaire facturé</label><input id="bc_owner" name="owner_name" x-ref="n" class="input" required maxlength="150"></div>
                <div><label class="label" for="bc_email">Email du propriétaire</label><input id="bc_email" name="owner_email" x-ref="e" type="email" class="input"></div>
                <x-field name="label" label="Formule" value="Pension box + paddock" required />
                <x-field name="monthly_amount" type="number" step="0.01" min="0" label="Montant mensuel (€)" required inputmode="decimal" />
                <x-field name="starts_on" type="date" label="Depuis le" :value="now()->startOfMonth()->toDateString()" required />
            </div>
            <p class="text-xs text-slate-500">Si le propriétaire a un compte Jack&Cie, il reçoit ses relevés dans l'application et la somme apparaît dans son budget.</p>
            <div class="flex gap-2"><button class="btn-primary flex-1">Enregistrer</button><button type="button" class="btn-ghost" onclick="this.closest('dialog').close()">Annuler</button></div>
        </form>
    </dialog>

    {{-- Nouvelle prestation --}}
    <dialog id="new-charge" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl p-0 backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('boarding.charges.store') }}" class="space-y-3 p-5" x-data>
            @csrf
            <h2 class="text-[17px] font-semibold">Prestation à refacturer</h2>
            <x-select name="horse_id" label="Cheval" :options="$horses->mapWithKeys(fn ($h) => [$h->id => $h->shortName()])" placeholder="Choisir…" required />
            <x-field name="label" id="ch_label" label="Prestation" required maxlength="150" list="charge-presets" placeholder="Sortie au paddock, complément…" />
            <datalist id="charge-presets">@foreach (['Sortie au paddock', 'Mise au marcheur', 'Complément alimentaire', 'Copeaux supplémentaires', 'Travail du cheval', 'Présence vétérinaire / maréchal', 'Douche et soins'] as $p)<option value="{{ $p }}"></option>@endforeach</datalist>
            <div class="grid grid-cols-3 gap-3">
                <x-field name="charged_on" type="date" label="Date" :value="now()->toDateString()" required class="col-span-3 sm:col-span-1" />
                <x-field name="quantity" type="number" step="0.5" min="0.5" label="Quantité" value="1" required />
                <x-field name="unit_price" type="number" step="0.01" min="0" label="Prix unitaire (€)" required inputmode="decimal" />
            </div>
            <div class="flex gap-2"><button class="btn-primary flex-1">Ajouter</button><button type="button" class="btn-ghost" onclick="this.closest('dialog').close()">Annuler</button></div>
        </form>
    </dialog>
</x-layouts.app>
