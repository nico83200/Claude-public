{{-- Cours : coût d'après les tarifs de l'enseignant, à la charge du cavalier par défaut (App\Services\LessonCosts). --}}
@php
    $costs = app(\App\Services\LessonCosts::class);
    $me = auth()->user();
    $charges = $costs->visibleCharges($session, $me);
    $mayEdit = $canEdit && $costs->canSetCost($session, $me);
    $payer = $costs->payerOf($session);
    $estimate = $mayEdit || $payer?->id === $me->id ? $costs->sessionPrice($session) : null;
    $money = fn ($v) => number_format((float) $v, 2, ',', ' ').' €';
@endphp
@if ($charges->isNotEmpty() || $mayEdit || $estimate)
    <x-section title="Coût du cours" data-lesson-cost>
        @foreach ($charges as $c)
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 border-t border-slate-100 py-2.5 text-sm first:border-0" data-lesson-charge="{{ $c->kind }}">
                <span class="min-w-0 flex-1">
                    <span class="block font-medium {{ $c->trashed() ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ $c->label }}</span>
                    <span class="block text-xs text-slate-500">À la charge de {{ $c->payer_id === $me->id ? 'vous' : $c->payer?->name }}{{ $c->expense_id && ! $c->trashed() ? ' · aussi dans le budget du cheval' : '' }}{{ $c->trashed() ? ' · retiré' : '' }}</span>
                </span>
                <span class="font-semibold text-slate-900">{{ $money($c->amount) }}</span>
                @if ($c->trashed())
                    @if (in_array($me->id, [$c->payer_id, $c->created_by], true))
                        <form method="POST" action="{{ route('lesson-costs.restore', $c) }}">@csrf<button class="btn-ghost min-h-10 px-2 text-sm" data-charge-restore>Rétablir</button></form>
                    @endif
                @elseif ($c->payer_id === $me->id)
                    {{-- Un toucher : payé, ou de nouveau à payer. --}}
                    <form method="POST" action="{{ route('lesson-costs.paid', $c) }}">@csrf<input type="hidden" name="paid" value="{{ $c->paid_on ? 0 : 1 }}">
                        <button class="pill min-h-10 px-3 text-xs {{ $c->paid_on ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}" data-charge-paid aria-label="{{ $c->paid_on ? 'Payé : remettre à payer' : 'À payer : marquer payé' }}">{{ $c->paid_on ? 'Payé' : 'À payer' }}</button>
                    </form>
                @else
                    <x-badge :color="$c->paid_on ? 'green' : 'amber'">{{ $c->paid_on ? 'Payé' : 'À payer' }}</x-badge>
                @endif
            </div>
        @endforeach
        @if ($charges->isEmpty())
            @if ($estimate)
                <p class="text-sm text-slate-700" data-lesson-estimate>Coût prévu : <strong>{{ $money($estimate['amount']) }}</strong> · {{ $estimate['label'] }}. Compté quand la séance est faite, à la charge de {{ $payer?->id === $me->id ? 'vous' : $payer?->name }}.</p>
            @elseif ($session->coach && $mayEdit && $me->memberships->contains('organization_id', $session->coach->organization_id))
                <p class="text-sm text-slate-600">Pas encore de tarif pour {{ $session->coach->fullName() }} : <a href="{{ route('professionals.show', $session->coach) }}#tarifs" class="link">ajoutez ses tarifs</a>, ou indiquez le prix ci-dessous.</p>
            @elseif ($mayEdit)
                <p class="text-sm text-slate-600">Indiquez le prix du cours ci-dessous, ou choisissez un enseignant de vos intervenants (avec ses tarifs) pour qu'il soit calculé.</p>
            @endif
        @endif
        @if ($mayEdit)
            <details class="group mt-3 rounded-2xl bg-slate-50 p-3" data-lesson-cost-form>
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between text-sm font-medium text-slate-700">Prix ou personne qui paie<x-icon name="chevron" class="size-4 transition group-open:rotate-90" /></summary>
                <form method="POST" action="{{ route('sessions.cost', $session) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <x-field name="lesson_price" type="number" step="0.01" min="0" label="Prix du cours (€)" :value="$session->lesson_price" inputmode="decimal"
                        :placeholder="$estimate && ! $estimate['manual'] ? number_format($estimate['amount'], 2, ',', '') : 'Tarif de l\'enseignant'" help="Vide : tarif de l'enseignant. 0 : cours offert." />
                    <x-select name="payer_id" label="À la charge de" :options="$costs->payerChoices($session, $me)" :value="$payer?->id" />
                    <button class="btn-primary sm:col-span-2 sm:w-fit">Enregistrer</button>
                </form>
            </details>
        @endif
        <a href="{{ route('lesson-costs.index') }}" class="mt-2 inline-flex min-h-11 items-center gap-1 text-sm link">Tous mes frais de cours <x-icon name="chevron" class="size-4" /></a>
    </x-section>
@endif
