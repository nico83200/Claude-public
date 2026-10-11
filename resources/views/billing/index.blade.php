@php $eur = fn ($v, $c = 'EUR') => number_format((float) $v, 2, ',', ' ').' '.($c === 'EUR' ? '€' : $c); @endphp
<x-layouts.app title="Abonnement">
    <x-page-header title="Abonnement" :subtitle="$org->name" />
    @if ($cancelled)<div class="mb-4 rounded-lg bg-slate-100 p-3 text-sm">Paiement annulé : aucun montant n'a été prélevé.</div>@endif
    @unless ($stripeReady)<div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Le paiement en ligne n'est pas encore configuré sur cette instance. Contactez le support pour souscrire.</div>@endunless

    <div class="grid gap-4 lg:grid-cols-3">
        <x-section title="Situation actuelle">
            <p class="text-lg font-semibold">{{ $ent['plan']?->name ?? 'Aucune offre' }}</p>
            <p class="text-sm"><x-badge :color="$ent['writable'] ? 'green' : 'amber'">{{ \App\Models\License::STATUSES[$ent['status']] ?? 'Aucune licence' }}</x-badge> @if ($ent['source'] === 'manual')<x-badge color="purple">Licence attribuée</x-badge>@elseif ($ent['source'] === 'code')<x-badge color="purple">Accès offert</x-badge>@endif</p>
            @if (in_array($ent['source'], ['manual', 'code'], true) && $ent['license']?->ends_at)<p class="mt-1 text-sm text-slate-600">Jusqu'au {{ $ent['license']->ends_at->format('d/m/Y') }}</p>@endif
            @if ($ent['read_only_reason'])<p class="mt-2 text-sm text-amber-800">{{ $ent['read_only_reason'] }}</p>@endif
            @if ($subscription)
                <div class="mt-3 space-y-1 text-sm text-slate-600">
                    <p>{{ $eur($subscription->unit_amount, $subscription->currency) }} / {{ $subscription->interval === 'year' ? 'an' : 'mois' }}</p>
                    @if ($subscription->stripe_status === 'trialing' && $subscription->trial_ends_at)<p>Essai jusqu'au {{ $subscription->trial_ends_at->format('d/m/Y') }}</p>@endif
                    @if ($subscription->current_period_end)<p>{{ $subscription->cancel_at_period_end ? 'Se termine le' : 'Renouvellement le' }} {{ $subscription->current_period_end->format('d/m/Y') }}</p>@endif
                    @if ($ent['license']?->grace_ends_at)<p class="text-amber-700">Période de grâce jusqu'au {{ $ent['license']->grace_ends_at->format('d/m/Y') }}</p>@endif
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($org->stripeCustomer)<form method="POST" action="{{ route('billing.portal') }}">@csrf<button class="btn-secondary">Moyen de paiement et factures</button></form>@endif
                    @if ($subscription->cancel_at_period_end)
                        <form method="POST" action="{{ route('billing.resume') }}">@csrf<button class="btn-primary">Reprendre l'abonnement</button></form>
                    @else
                        <form method="POST" action="{{ route('billing.cancel') }}" onsubmit="return confirm('Résilier à la fin de la période en cours ?')">@csrf<button class="btn-ghost text-red-600">Résilier</button></form>
                    @endif
                </div>
            @endif
        </x-section>
        <x-section title="Utilisation">
            @foreach (['horses' => ['Chevaux actifs', 'max_horses', ''], 'members' => ['Membres', 'max_members', ''], 'storage_mb' => ['Stockage', 'storage_mb', ' Mo']] as $k => [$label, $limitKey, $unit])
                @php $limit = $ent['limits'][$limitKey]; $pct = $limit ? min(100, round($usage[$k] / max(1, $limit) * 100)) : 0; @endphp
                <div class="mb-3 text-sm"><div class="flex justify-between"><span>{{ $label }}</span><span>{{ $usage[$k] }}{{ $unit }} / {{ $limit ?? '∞' }}{{ $limit ? $unit : '' }}</span></div>
                    @if ($limit)<div class="mt-1 h-2 rounded bg-slate-100"><div class="h-2 rounded {{ $pct >= 90 ? 'bg-red-500' : 'bg-brand-500' }}" style="width: {{ $pct }}%"></div></div>@endif</div>
            @endforeach
            <p class="text-xs text-slate-500">Fonctionnalités : {{ collect($ent['features'])->map(fn ($f) => \App\Models\SubscriptionPlan::FEATURES[$f] ?? $f)->implode(', ') ?: '—' }}</p>
        </x-section>
        <x-section title="Historique des paiements">
            @forelse ($payments as $p)
                <div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm first:border-0">
                    <span>{{ ($p->paid_at ?? $p->created_at)->format('d/m/Y') }} <x-badge :color="['paid' => 'green', 'failed' => 'red', 'refunded' => 'blue'][$p->status] ?? 'slate'">{{ ['paid' => 'Payé', 'failed' => 'Échec', 'open' => 'En attente', 'refunded' => 'Remboursé', 'void' => 'Annulé', 'uncollectible' => 'Irrécouvrable', 'draft' => 'Brouillon'][$p->status] }}</x-badge></span>
                    <span>{{ $eur($p->status === 'paid' ? $p->amount_paid : $p->amount_due, $p->currency) }} @if ($p->hosted_invoice_url)<a href="{{ $p->hosted_invoice_url }}" target="_blank" rel="noopener" class="ml-1 text-xs link">Facture</a>@endif</span>
                </div>
            @empty <p class="text-sm text-slate-500">Aucun paiement.</p> @endforelse
        </x-section>
    </div>

    @unless ($subscription)
        <details class="card mt-6 p-4" @if ($errors->has('code')) open @endif>
            <summary class="cursor-pointer font-medium">J'ai un code d'accès</summary>
            <form method="POST" action="{{ route('billing.redeem') }}" class="mt-3 flex flex-wrap items-end gap-2">
                @csrf
                <x-field name="code" label="Code" required autocomplete="off" class="min-w-0 flex-1" />
                <button class="btn-primary">Activer</button>
            </form>
            <p class="mt-2 text-xs text-slate-500">Un code d'accès offre une offre pendant une durée limitée, sans carte bancaire. Pour un code de réduction, saisissez-le au moment de souscrire.</p>
        </details>
    @endunless

    @php
        $focusLimit = $focus && ! array_key_exists($focus, \App\Models\SubscriptionPlan::FEATURES);
        $unlocks = fn ($plan) => $focus && ($focusLimit ? ($plan->{$focus} === null || $plan->{$focus} > ($ent['limits'][$focus] ?? PHP_INT_MAX)) : in_array($focus, $plan->features ?? [], true));
    @endphp
    <h2 id="offres" class="mt-8 mb-3 scroll-mt-24 text-lg font-semibold">{{ $subscription ? 'Changer d\'offre' : 'Choisir une offre' }}</h2>
    @if ($focus)
        <div class="mb-4 flex items-start gap-3 rounded-2xl bg-gold-50 p-4 text-sm text-slate-700 ring-1 ring-gold-200">
            <x-icon name="sparkles" class="size-5 shrink-0 text-gold-600" />
            <p><strong class="text-slate-900">{{ \App\Services\Upsell::PITCHES[$focus][0] }}</strong> — {{ \App\Services\Upsell::PITCHES[$focus][1] }} Les offres qui le permettent sont signalées ci-dessous.@if (! $subscription && $trialAvailable) Essai gratuit, sans engagement.@endif</p>
        </div>
    @endif
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" x-data="{ interval: 'month' }">
        <div class="flex gap-2 sm:col-span-2 lg:col-span-3">
            <button type="button" @click="interval = 'month'" :class="interval === 'month' ? 'btn-primary' : 'btn-secondary'">Mensuel</button>
            <button type="button" @click="interval = 'year'" :class="interval === 'year' ? 'btn-primary' : 'btn-secondary'">Annuel</button>
        </div>
        @foreach ($plans as $plan)
            <div class="card flex flex-col p-5 {{ $ent['plan']?->id === $plan->id || $unlocks($plan) ? 'border-brand-500 ring-1 ring-brand-500' : '' }}" @if ($unlocks($plan)) data-unlocks="{{ $focus }}" @endif>
                @if ($unlocks($plan))<p class="mb-2"><x-badge color="green">Inclut ce qu'il vous faut</x-badge></p>@endif
                <p class="text-lg font-bold">{{ $plan->name }}</p>
                <p class="text-sm text-slate-600">{{ $plan->description }}</p>
                <p class="mt-3"><span class="text-2xl font-bold" x-text="interval === 'year' ? @js($plan->price_yearly ? $eur($plan->price_yearly, $plan->currency).' / an' : 'Non disponible') : @js($plan->price_monthly ? $eur($plan->price_monthly, $plan->currency).' / mois' : 'Non disponible')"></span></p>
                <p class="text-xs text-slate-500">{{ $plan->max_horses ?? '∞' }} cheval(aux) · {{ $plan->max_members ?? '∞' }} membre(s){{ $plan->trial_days ? ' · '.$plan->trial_days.' j d\'essai (1re souscription)' : '' }}</p>
                @if ($plan->renewal_terms)<p class="mt-1 text-xs text-slate-500">{{ $plan->renewal_terms }}</p>@endif
                <ul class="mt-3 space-y-1 text-sm text-slate-700">
                    @foreach (\App\Models\SubscriptionPlan::FEATURES as $fk => $flabel)
                        @if (in_array($fk, $plan->features ?? [], true))
                            <li class="flex items-start gap-2 {{ $focus === $fk ? 'font-semibold text-brand-900' : '' }}"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-brand-600" /> {{ $flabel }}@unless (in_array($fk, $ent['features'], true) || ! in_array($fk, \App\Services\Upsell::GATED, true))<span class="ml-auto shrink-0 text-xs font-medium text-gold-600">Nouveau pour vous</span>@endunless</li>
                        @endif
                    @endforeach
                </ul>
                <form method="POST" action="{{ $subscription ? route('billing.swap') : route('billing.checkout') }}" class="mt-auto space-y-2 pt-4">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}"><input type="hidden" name="interval" :value="interval">
                    @if (! $subscription && $stripeReady)
                        <input name="promo_code" placeholder="Code de réduction (optionnel)" aria-label="Code de réduction" value="{{ old('plan_id') == $plan->id ? old('promo_code') : '' }}" class="input text-sm uppercase placeholder:normal-case" autocomplete="off">
                        @if ($errors->has('promo_code') && old('plan_id') == $plan->id)<p class="text-sm text-red-600">{{ $errors->first('promo_code') }}</p>@endif
                    @endif
                    @unless ($subscription)<label class="flex items-start gap-2 text-xs text-slate-600"><input type="checkbox" name="accept" value="1" required class="mt-0.5 rounded"> <span>J'accepte les <a href="{{ route('legal.terms') }}" target="_blank" class="link">conditions</a> : renouvellement automatique, résiliable à tout moment.</span></label>@endunless
                    <button class="btn-primary w-full" @disabled(! $stripeReady)>{{ $subscription ? 'Passer à cette offre' : ($trialAvailable && $plan->trial_days ? 'Essayer '.$plan->trial_days.' jours gratuitement' : 'Souscrire') }}</button>
                </form>
            </div>
        @endforeach
    </div>
    <p class="mt-4 text-xs text-slate-500">Paiement sécurisé par Stripe : aucune donnée bancaire n'est stockée par {{ $appName }}. En fin d'abonnement, vos données sont conservées en lecture seule et restent exportables pendant au moins {{ config('equine.billing.recovery_days') }} jours.</p>
</x-layouts.app>
