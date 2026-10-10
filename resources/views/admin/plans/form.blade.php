<x-layouts.app :title="($plan->exists ? $plan->name : 'Nouvelle offre').' · Admin'">
    <x-page-header :title="$plan->exists ? $plan->name : 'Nouvelle offre'" :back="route('admin.plans.index')">
        @if ($plan->exists)<form method="POST" action="{{ route('admin.plans.archive', $plan) }}" onsubmit="return confirm('Confirmer ?')">@csrf<button class="btn-secondary">{{ $plan->archived_at ? 'Désarchiver' : 'Archiver' }}</button></form>@endif
    </x-page-header>
    @include('admin._nav')
    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="space-y-4" x-data="{ pm: @js((string) $plan->price_monthly), py: @js((string) $plan->price_yearly), om: @js((string) $plan->price_monthly), oy: @js((string) $plan->price_yearly) }">
        @csrf @if ($plan->exists) @method('PUT') @endif
        <div class="grid gap-4 lg:grid-cols-2">
            <x-section title="Offre">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="name" label="Nom" :value="$plan->name" required class="sm:col-span-2" />
                    <x-textarea name="description" label="Description" :value="$plan->description" class="sm:col-span-2" />
                    <x-select name="audience" label="Public" :options="['individual' => 'Particuliers', 'stable' => 'Écuries', 'any' => 'Tous']" :value="$plan->audience" />
                    <x-field name="sort_order" type="number" label="Ordre d'affichage" :value="$plan->sort_order" />
                    <x-field name="trial_days" type="number" label="Essai (jours)" :value="$plan->trial_days" min="0" max="90" />
                    <x-field name="currency" label="Devise" :value="$plan->currency" maxlength="3" />
                    <x-textarea name="renewal_terms" label="Conditions de renouvellement" :value="$plan->renewal_terms" class="sm:col-span-2" />
                </div>
                <div class="mt-3"><x-checkbox name="is_active" label="Active (souscriptible)" :checked="$plan->is_active" /><x-checkbox name="is_public" label="Visible sur la page tarifs" :checked="$plan->is_public" /><x-checkbox name="is_default_free" label="Offre gratuite par défaut (sans licence)" :checked="$plan->is_default_free" /></div>
            </x-section>
            <x-section title="Tarifs">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="label" for="pm">Prix mensuel</label><input id="pm" name="price_monthly" type="number" step="0.01" min="0" x-model="pm" class="input">@error('price_monthly')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="py">Prix annuel</label><input id="py" name="price_yearly" type="number" step="0.01" min="0" x-model="py" class="input"></div>
                    <x-field name="stripe_price_monthly_id" label="ID prix Stripe mensuel" :value="$plan->stripe_price_monthly_id" placeholder="price_…" />
                    <x-field name="stripe_price_yearly_id" label="ID prix Stripe annuel" :value="$plan->stripe_price_yearly_id" placeholder="price_…" />
                </div>
                @if ($stripeReady)<div class="mt-2"><x-checkbox name="create_stripe_prices" label="Créer automatiquement le produit et les prix dans Stripe" /></div>@else<p class="mt-2 text-xs text-slate-500">Stripe non configuré : renseignez les identifiants de prix créés dans le tableau de bord Stripe.</p>@endif
                @if ($plan->exists)
                <div x-show="pm !== om || py !== oy" x-cloak class="mt-4 space-y-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm">
                    <p class="font-semibold text-amber-900">Modification tarifaire — {{ $subscribers ?? 0 }} abonné(s) actif(s)</p>
                    <p class="text-amber-900">Les prix Stripe sont immuables : un nouveau prix sera utilisé pour les nouvelles souscriptions. Pour les abonnés existants :</p>
                    <label class="flex gap-2"><input type="radio" name="existing_subscribers" value="keep_old_price" checked> Conserver leur tarif actuel (recommandé)</label>
                    <label class="flex gap-2"><input type="radio" name="existing_subscribers" value="migrate_after_notice"> Les migrer après information (préavis ≥ 30 jours)</label>
                    <x-field name="migration_effective_on" type="date" label="Date d'application aux abonnés existants" />
                </div>
                @endif
            </x-section>
            <x-section title="Limites (vide = illimité)">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="max_horses" type="number" label="Chevaux actifs" :value="$plan->max_horses" min="0" />
                    <x-field name="max_members" type="number" label="Membres" :value="$plan->max_members" min="0" />
                    <x-field name="max_invitations" type="number" label="Partages / invitations" :value="$plan->max_invitations" min="0" />
                    <x-field name="storage_mb" type="number" label="Stockage (Mo)" :value="$plan->storage_mb" min="0" />
                    <x-field name="history_months" type="number" label="Historique (mois, informatif)" :value="$plan->history_months" min="0" />
                </div>
            </x-section>
            <x-section title="Fonctionnalités">
                @foreach (\App\Models\SubscriptionPlan::FEATURES as $k => $l)<x-checkbox name="features[]" :value="$k" :label="$l" :checked="in_array($k, $plan->features ?? [])" />@endforeach
            </x-section>
        </div>
        <button class="btn-primary">Enregistrer</button>
    </form>
    @if ($plan->exists && ($priceChanges ?? collect())->isNotEmpty())
        <x-section title="Historique tarifaire" class="mt-4">
            @foreach ($priceChanges as $c)<p class="text-sm">{{ $c->created_at->format('d/m/Y') }} — {{ $c->old_price_monthly ?? '—' }} → {{ $c->new_price_monthly ?? '—' }} €/mois, {{ $c->old_price_yearly ?? '—' }} → {{ $c->new_price_yearly ?? '—' }} €/an · {{ $c->existing_subscribers === 'keep_old_price' ? 'abonnés existants conservés' : 'migration le '.$c->migration_effective_on?->format('d/m/Y') }} · {{ $c->author?->name }}</p>@endforeach
        </x-section>
    @endif
</x-layouts.app>
