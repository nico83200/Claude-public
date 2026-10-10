<x-layouts.app title="Codes promo · Admin">
    <x-page-header title="Codes promo et accès offerts" />
    @include('admin._nav')

    <details class="card mb-5 p-4 sm:p-5" @if ($errors->any()) open @endif>
        <summary class="cursor-pointer font-semibold">Créer un code</summary>
        <form method="POST" action="{{ route('admin.promo-codes.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2" x-data="{ type: @js(old('type', 'free_access')), kind: @js(old('discount_kind', 'percent')), duration: @js(old('duration', 'once')) }">
            @csrf
            <div class="sm:col-span-2 grid gap-2 sm:grid-cols-2">
                @foreach (\App\Models\PromoCode::TYPES as $t => $tl)
                    <label class="card flex cursor-pointer items-start gap-3 p-3" :class="type === '{{ $t }}' && 'ring-2 ring-brand-500'">
                        <input type="radio" name="type" value="{{ $t }}" x-model="type" class="mt-1">
                        <span><span class="font-medium">{{ $tl }}</span><span class="block text-xs text-slate-500">{{ $t === 'discount' ? 'Remise appliquée par Stripe sur le paiement (pourcentage ou montant).' : 'Licence offerte pendant N jours, sans carte bancaire. Expire automatiquement.' }}</span></span>
                    </label>
                @endforeach
            </div>
            <x-field name="code" label="Code" :value="$suggested" required help="Lettres, chiffres, - et _ ; converti en majuscules." />
            <x-field name="label" label="Libellé interne" required placeholder="Salon du cheval 2026" />

            <template x-if="type === 'free_access'"><div class="contents">
                <x-select name="subscription_plan_id" label="Offre accordée" :options="$plans" placeholder="Choisir…" />
                <x-field name="free_days" type="number" label="Durée offerte (jours)" value="30" min="1" max="3650" />
            </div></template>

            <template x-if="type === 'discount'"><div class="contents">
                @unless ($stripeReady)<p class="sm:col-span-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Stripe n'est pas configuré : configurez-le dans <a href="{{ route('admin.integrations.edit') }}" class="link">Intégrations</a> avant de créer une réduction.</p>@endunless
                <x-select name="discount_kind" label="Type de remise" :options="['percent' => 'Pourcentage', 'amount' => 'Montant fixe (€)']" x-model="kind" />
                <div x-show="kind === 'percent'"><x-field name="percent_off" type="number" step="0.01" label="Réduction (%)" min="1" max="100" /></div>
                <div x-show="kind === 'amount'"><x-field name="amount_off" type="number" step="0.01" label="Réduction (€)" min="0.5" /></div>
                <x-select name="duration" label="Appliquée" :options="['once' => 'Sur la première facture', 'repeating' => 'Pendant plusieurs mois', 'forever' => 'Pendant tout l\'abonnement']" x-model="duration" />
                <div x-show="duration === 'repeating'"><x-field name="duration_months" type="number" label="Nombre de mois" min="1" max="36" /></div>
                <fieldset class="sm:col-span-2"><legend class="label">Offres concernées (aucune cochée = toutes)</legend>
                    <div class="flex flex-wrap gap-x-4">@foreach ($plans as $id => $name)<x-checkbox name="plan_ids[]" :value="$id" :label="$name" :checked="in_array($id, old('plan_ids', []))" />@endforeach</div>
                </fieldset>
            </div></template>

            <x-field name="starts_at" type="date" label="Valable à partir du (optionnel)" />
            <x-field name="expires_at" type="date" label="Valable jusqu'au (optionnel)" />
            <x-field name="max_redemptions" type="number" label="Nombre maximal d'utilisations (vide = illimité)" min="1" />
            <div class="self-end"><x-checkbox name="first_subscription_only" label="Réservé aux nouveaux clients" /></div>
            <div class="sm:col-span-2"><button class="btn-primary">Créer le code</button></div>
        </form>
    </details>

    <div class="mb-3 flex gap-2 text-sm">
        <a href="{{ route('admin.promo-codes.index') }}" class="{{ request('type') ? 'btn-ghost' : 'btn-secondary' }}">Tous</a>
        @foreach (\App\Models\PromoCode::TYPES as $t => $tl)<a href="{{ route('admin.promo-codes.index', ['type' => $t]) }}" class="{{ request('type') === $t ? 'btn-secondary' : 'btn-ghost' }}">{{ $t === 'discount' ? 'Réductions' : 'Accès gratuits' }}</a>@endforeach
    </div>
    @if ($codes->isEmpty())
        <x-empty title="Aucun code">Créez un code de réduction ou un accès gratuit temporaire.</x-empty>
    @else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Code</th><th>Avantage</th><th>Utilisations</th><th>Validité</th><th>État</th><th></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @foreach ($codes as $c)
            <tr>
                <td><span class="font-mono font-semibold">{{ $c->code }}</span><span class="block text-xs text-slate-500">{{ $c->label }}</span></td>
                <td class="text-sm">{{ $c->summary() }}</td>
                <td>{{ $c->redemptions_count }}{{ $c->max_redemptions ? ' / '.$c->max_redemptions : '' }}</td>
                <td class="text-xs">{{ $c->starts_at?->format('d/m/Y') ?? '—' }} → {{ $c->expires_at?->format('d/m/Y') ?? '∞' }}</td>
                <td>@if ($r = $c->unavailableReason())<x-badge color="amber" :title="$r">Inutilisable</x-badge>@else<x-badge color="green">Valable</x-badge>@endif</td>
                <td><a href="{{ route('admin.promo-codes.show', $c) }}" class="link">Détail</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    {{ $codes->links() }}
    @endif
</x-layouts.app>
