<x-layouts.app title="Paramètres · Admin">
    <x-page-header title="Paramètres globaux" />
    @include('admin._nav')
    <form method="POST" action="{{ route('admin.settings.update') }}" class="grid gap-4 lg:grid-cols-2">
        @csrf @method('PUT')
        <x-section title="Identité de l'application">
            <div class="space-y-3">
                <x-field name="app_name" label="Nom de l'application" :value="$settings['app_name'] ?? config('app.name')" required />
                <x-field name="logo_url" type="url" label="URL du logo (https)" :value="$settings['logo_url']" />
                <x-field name="primary_color" label="Couleur principale (#RRGGBB)" :value="$settings['primary_color']" />
                <x-field name="support_email" type="email" label="Email du support" :value="$settings['support_email']" />
                <x-field name="support_phone" label="Téléphone du support" :value="$settings['support_phone']" />
            </div>
        </x-section>
        <x-section title="Informations légales (RGPD)">
            <div class="space-y-3">
                <x-field name="legal_entity" label="Raison sociale" :value="$settings['legal_entity']" />
                <x-textarea name="legal_address" label="Adresse" :value="$settings['legal_address']" rows="2" />
                <x-field name="dpo_email" type="email" label="Contact données personnelles / DPO" :value="$settings['dpo_email']" />
                <x-textarea name="subprocessors" label="Sous-traitants (un par ligne)" :value="$settings['subprocessors']" rows="4" />
            </div>
        </x-section>
        <x-section title="Fonctionnalités activables">
            @foreach ($flags as $flag)<x-checkbox :name="'flags['.$flag->key.']'" :label="$flag->label" :checked="$flag->enabled_globally" />@endforeach
            <p class="mt-2 text-xs text-slate-500">Paramètres techniques (stockage, délais hors ligne, sources de recherche, email, Stripe) : variables d'environnement, voir <code>.env.example</code>.</p>
            <dl class="mt-3 grid grid-cols-2 gap-1 text-xs text-slate-600">
                <dt>Email</dt><dd>{{ config('mail.default') }}</dd>
                <dt>Stripe</dt><dd>{{ config('services.stripe.secret') ? 'configuré' : 'non configuré' }} / webhook {{ config('services.stripe.webhook_secret') ? 'configuré' : 'non configuré' }}</dd>
                <dt>Recherche Wikidata</dt><dd>{{ config('equine.horse_search.wikidata') ? 'active' : 'inactive' }}</dd>
                <dt>Recherche web (Brave)</dt><dd>{{ config('equine.horse_search.brave_api_key') ? 'active' : 'clé absente' }}</dd>
                <dt>Validité hors ligne</dt><dd>{{ config('equine.offline.ttl_hours') }} h</dd>
                <dt>Taille max. des fichiers</dt><dd>{{ config('equine.uploads.max_kb') }} Ko</dd>
                <dt>Grâce / récupération</dt><dd>{{ config('equine.billing.grace_days') }} j / {{ config('equine.billing.recovery_days') }} j</dd>
            </dl>
        </x-section>
        <div class="lg:col-span-2"><button class="btn-primary">Enregistrer</button></div>
    </form>
</x-layouts.app>
