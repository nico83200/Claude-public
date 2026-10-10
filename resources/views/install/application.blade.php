<x-layouts.install :step="3" title="Votre application">
    <form method="POST" action="{{ route('install.application.save') }}" class="space-y-5">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="app_name" label="Nom affiché" value="Jack&Cie" required />
            <x-field name="app_url" type="url" label="Adresse du site" :value="$url" required help="Avec https:// en production." />
            <x-field name="support_email" type="email" label="Email du support" />
            <x-select name="environment" label="Environnement" :options="['production' => 'Production (recommandé)', 'local' => 'Test / recette']" value="production" />
        </div>

        <details class="rounded-xl border border-sand-200 p-4" @if ($errors->hasAny(['mail_host', 'mail_from'])) open @endif>
            <summary class="cursor-pointer font-medium">Envoi des emails <span class="text-sm font-normal text-slate-500">— facultatif, modifiable plus tard</span></summary>
            <p class="mt-2 mb-3 text-xs text-slate-500">Indispensable en production pour la vérification des comptes et les invitations. Sans réglage, les emails sont écrits dans le journal.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field name="mail_host" label="Serveur SMTP" placeholder="smtp.exemple.fr" />
                <x-field name="mail_port" type="number" label="Port" value="587" />
                <x-field name="mail_username" label="Identifiant" autocomplete="off" />
                <x-field name="mail_password" type="password" label="Mot de passe" autocomplete="new-password" />
                <x-field name="mail_from" type="email" label="Adresse d'expédition" placeholder="contact@votre-domaine.fr" class="sm:col-span-2" />
            </div>
        </details>

        <details class="rounded-xl border border-sand-200 p-4" @if ($errors->hasAny(['stripe_key', 'stripe_secret', 'stripe_webhook_secret'])) open @endif>
            <summary class="cursor-pointer font-medium">Paiements Stripe <span class="text-sm font-normal text-slate-500">— facultatif</span></summary>
            <p class="mt-2 mb-3 text-xs text-slate-500">Dashboard Stripe › Développeurs › Clés API et Webhooks. Sans clés, l'application fonctionne avec l'offre gratuite et les licences attribuées par l'administrateur.</p>
            <div class="grid gap-3">
                <x-field name="stripe_key" label="Clé publiable (pk_…)" autocomplete="off" />
                <x-field name="stripe_secret" type="password" label="Clé secrète (sk_…)" autocomplete="off" />
                <x-field name="stripe_webhook_secret" type="password" label="Secret du webhook (whsec_…)" autocomplete="off" />
            </div>
        </details>
        <button class="btn-primary w-full">Enregistrer et continuer</button>
    </form>
</x-layouts.install>
