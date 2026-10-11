<x-layouts.app title="Vérifier la facture">
    <x-page-header title="Vérifier la facture" subtitle="Lue par l'assistant : contrôlez avant d'enregistrer" :back="route('budget.index')" />
    @php
        $conf = ['high' => ['Lecture sûre', 'green'], 'medium' => ['À vérifier', 'amber'], 'low' => ['Lecture incertaine', 'red']][$d['confidence']] ?? ['À vérifier', 'amber'];
    @endphp
    <div class="grid gap-4 lg:grid-cols-5">
        <x-section class="lg:col-span-2">
            <div class="mb-3 flex items-center justify-between gap-2">
                <p class="flex items-center gap-2 font-semibold"><x-icon name="receipt" class="size-5 text-brand-700" /> {{ $d['supplier'] ?: 'Fournisseur non lu' }}</p>
                <x-badge :color="$conf[1]">{{ $conf[0] }}</x-badge>
            </div>
            @if ($d['summary'])<p class="text-sm text-slate-600">{{ $d['summary'] }}</p>@endif
            @if ($d['lines'])
                <ul class="mt-3 divide-y divide-slate-100 rounded-2xl bg-slate-50 px-4 text-sm">
                    @foreach ($d['lines'] as $line)
                        <li class="flex justify-between gap-3 py-2"><span class="min-w-0">{{ $line['label'] }}</span><span class="shrink-0 tabular-nums">{{ number_format((float) $line['amount'], 2, ',', ' ') }} €</span></li>
                    @endforeach
                </ul>
            @endif
            <p class="mt-3 flex items-center justify-between rounded-2xl bg-brand-50 px-4 py-3 text-sm text-brand-900"><span>Total lu</span><strong class="text-lg tabular-nums">{{ number_format((float) $d['total_amount'], 2, ',', ' ') }} {{ $d['currency'] === 'EUR' || $d['currency'] === '' ? '€' : $d['currency'] }}</strong></p>
            <p class="mt-3 text-xs text-slate-500">Fichier : {{ $pending['name'] }}. Il sera joint à la dépense comme justificatif.</p>
        </x-section>

        <form method="POST" action="{{ route('budget.invoice.save', $token) }}" class="space-y-4 lg:col-span-3" x-data="{ care: {{ $care && $horseId ? 'true' : 'false' }}, horse: @js((string) ($horseId ?? '')) }">
            @csrf
            @if ($matches->isNotEmpty())
                <x-section title="Déjà saisie ?" data-merge>
                    <p class="-mt-1 mb-2 text-sm text-slate-500">Cette facture ressemble à une dépense déjà enregistrée : rattachez-la pour éviter un doublon. La dépense prendra les informations vérifiées ci-dessous et la facture en justificatif.</p>
                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 p-3 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" name="merge_expense_id" value="" @checked(! $matches[0]['same_amount']) class="size-5"> <span class="font-medium text-slate-900">Nouvelle dépense</span>
                        </label>
                        @foreach ($matches as $i => $m)
                            @php $e = $m['expense']; @endphp
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 p-3 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <input type="radio" name="merge_expense_id" value="{{ $e->id }}" @checked($i === 0 && $m['same_amount']) class="size-5">
                                <span class="min-w-0 flex-1"><span class="font-medium text-slate-900">Rattacher à : {{ $e->category->name }} · {{ $e->horse?->shortName() }}</span>
                                    <span class="block text-xs text-slate-500">{{ $e->spent_on->format('d/m/Y') }}{{ $e->supplier ? ' · '.$e->supplier : '' }}{{ $e->documents->isNotEmpty() ? ' · a déjà un justificatif' : '' }}</span></span>
                                <span class="shrink-0 font-semibold tabular-nums">{{ number_format((float) $e->amount, 2, ',', ' ') }} €</span>
                            </label>
                        @endforeach
                    </div>
                </x-section>
            @endif
            <x-section title="Dépense">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-select name="horse_id" label="Cheval" :options="$horses" :value="$horseId" :placeholder="$horseId ? null : 'Choisir…'" required x-model="horse" />
                    <x-select name="expense_category_id" label="Catégorie" :options="$categories" :value="$categoryId" required />
                    <x-field name="amount" type="number" step="0.01" min="0.01" label="Montant (€)" :value="$d['total_amount'] > 0 ? number_format((float) $d['total_amount'], 2, '.', '') : null" required inputmode="decimal" />
                    <x-field name="spent_on" type="date" label="Date" :value="$date" required />
                    <x-field name="supplier" label="Fournisseur" :value="$d['supplier']" maxlength="150" />
                    <x-professional-select label="Intervenant lié" :options="$professionals" :value="$professionalId" />
                    <x-field name="comment" label="Commentaire" :value="$d['summary']" maxlength="2000" class="sm:col-span-2" />
                    <div class="sm:col-span-2"><x-checkbox name="paid" label="Déjà payée" :checked="true" /></div>
                </div>
            </x-section>
            <x-section title="Carnet de santé" x-show="horse">
                <label class="flex items-start gap-3 text-sm"><input type="hidden" name="with_care" value="0"><input type="checkbox" name="with_care" value="1" x-model="care" class="mt-0.5 size-5 rounded"> <span><span class="font-medium text-slate-900">Enregistrer aussi le soin</span><span class="block text-slate-500">Le soin est ajouté à l'historique du cheval, avec le prochain rappel s'il y en a un.</span></span></label>
                <div x-show="care" x-cloak class="mt-3"><x-select name="care_category_id" label="Type de soin" :options="$careCategories" :value="$care?->id" placeholder="Choisir…" /></div>
            </x-section>
            <div class="flex flex-wrap gap-2">
                <button class="btn-primary"><x-icon name="check" class="size-4" /> Enregistrer</button>
            </div>
        </form>
    </div>
    <form method="POST" action="{{ route('budget.invoice.cancel', $token) }}" class="mt-3">@csrf @method('DELETE')<button class="btn-ghost text-sm">Abandonner cette facture</button></form>
</x-layouts.app>
