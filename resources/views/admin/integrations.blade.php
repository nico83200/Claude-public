@php $src = fn ($k) => ['admin' => 'réglé ici', 'env' => 'fichier .env', 'none' => 'non renseigné'][$sources[$k]]; @endphp
<x-layouts.app title="Intégrations · Admin">
    <x-page-header title="Intégrations" subtitle="Emails, paiements Stripe, connexion avec Google et Apple" />
    @include('admin._nav')
    <p class="mb-4 text-sm text-slate-600">Les valeurs saisies ici priment sur le fichier <code>.env</code> (qui reste la valeur de repli). Les mots de passe et clés secrètes sont chiffrés en base et ne sont jamais réaffichés.</p>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-section title="Envoi des emails">
            <form method="POST" action="{{ route('admin.integrations.mail') }}" class="grid gap-3 sm:grid-cols-2" x-data="{ mailer: @js(old('mailer', $mail['mailer'])) }">
                @csrf @method('PUT')
                <x-select name="mailer" label="Mode d'envoi" :options="['smtp' => 'Serveur SMTP', 'sendmail' => 'Sendmail (serveur local)', 'log' => 'Journal uniquement (aucun envoi)']" :value="$mail['mailer']" x-model="mailer" class="sm:col-span-2" :help="'Actuellement : '.$src('mail.mailer')" />
                <template x-if="mailer === 'smtp'"><div class="contents">
                    <x-field name="host" label="Serveur SMTP" :value="$mail['host']" placeholder="smtp.exemple.fr" />
                    <x-field name="port" type="number" label="Port" :value="$mail['port']" placeholder="587" />
                    <x-select name="encryption" label="Chiffrement" :options="['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'Aucun (déconseillé)']" :value="$mail['encryption']" />
                    <x-field name="username" label="Identifiant" :value="$mail['username']" autocomplete="off" />
                    <x-field name="password" type="password" label="Mot de passe" autocomplete="new-password" class="sm:col-span-2"
                        :help="$masked['mail.password'] ? 'Enregistré ('.$masked['mail.password'].') — laisser vide pour le conserver.' : ($configured['mail.password'] ? 'Défini dans le .env — laisser vide pour le conserver.' : 'Non renseigné.')" />
                    @if ($masked['mail.password'])<div class="sm:col-span-2"><x-checkbox name="clear_password" label="Effacer le mot de passe enregistré" /></div>@endif
                </div></template>
                <x-field name="from_address" type="email" label="Adresse d'expédition" :value="$mail['from_address']" required />
                <x-field name="from_name" label="Nom d'expéditeur" :value="$mail['from_name']" required />
                <div class="sm:col-span-2"><button class="btn-primary">Enregistrer</button></div>
            </form>
            <form method="POST" action="{{ route('admin.integrations.mail.test') }}" class="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4">
                @csrf
                <x-field name="to" type="email" label="Envoyer un email de test à" :value="auth()->user()->email" required class="min-w-0 flex-1" />
                <button class="btn-secondary">Envoyer le test</button>
            </form>
        </x-section>

        <x-section title="Paiements Stripe">
            <p class="mb-3 text-sm">
                <x-badge :color="$configured['stripe.secret'] ? 'green' : 'amber'">{{ $configured['stripe.secret'] ? 'Clé secrète configurée' : 'Non configuré' }}</x-badge>
                @if ($configured['stripe.secret'])<x-badge :color="$stripe['mode'] === 'live' ? 'purple' : 'blue'">Mode {{ $stripe['mode'] === 'live' ? 'production' : 'test' }}</x-badge>@endif
                <x-badge :color="$configured['stripe.webhook_secret'] ? 'green' : 'amber'">Webhook {{ $configured['stripe.webhook_secret'] ? 'configuré' : 'à configurer' }}</x-badge>
            </p>
            <form method="POST" action="{{ route('admin.integrations.stripe') }}" class="space-y-3">
                @csrf @method('PUT')
                <x-field name="key" label="Clé publique (pk_…)" :value="$stripe['key']" placeholder="pk_live_…" :help="'Actuellement : '.$src('stripe.key')" />
                <x-field name="secret" type="password" label="Clé secrète (sk_… ou clé restreinte rk_…)" autocomplete="new-password"
                    :help="$masked['stripe.secret'] ? 'Enregistrée ('.$masked['stripe.secret'].') — laisser vide pour la conserver.' : ($configured['stripe.secret'] ? 'Définie dans le .env — laisser vide pour la conserver.' : 'Non renseignée.')" />
                @if ($masked['stripe.secret'])<x-checkbox name="clear[]" value="secret" label="Effacer la clé secrète enregistrée (retour au .env)" />@endif
                <x-field name="webhook_secret" type="password" label="Secret de signature du webhook (whsec_…)" autocomplete="new-password"
                    :help="$masked['stripe.webhook_secret'] ? 'Enregistré ('.$masked['stripe.webhook_secret'].') — laisser vide pour le conserver.' : ($configured['stripe.webhook_secret'] ? 'Défini dans le .env — laisser vide pour le conserver.' : 'Non renseigné.')" />
                @if ($masked['stripe.webhook_secret'])<x-checkbox name="clear[]" value="webhook_secret" label="Effacer le secret de webhook enregistré" />@endif
                <button class="btn-primary">Enregistrer</button>
            </form>
            <form method="POST" action="{{ route('admin.integrations.stripe.test') }}" class="mt-3">@csrf<button class="btn-secondary" @disabled(! $configured['stripe.secret'])>Tester la connexion</button></form>

            <div class="mt-4 border-t border-slate-100 pt-4 text-sm">
                <p class="font-medium">Webhook à déclarer dans Stripe (Développeurs › Webhooks)</p>
                <p class="mt-1 break-all rounded bg-slate-100 px-2 py-1 font-mono text-xs" x-data><span x-ref="u">{{ $webhookUrl }}</span> <button type="button" class="ml-1 link" @click="navigator.clipboard?.writeText($refs.u.textContent)">Copier</button></p>
                <p class="mt-2 text-xs text-slate-500">Événements : {{ implode(', ', $events) }}</p>
                <p class="mt-2 text-xs text-slate-500">Les offres (Administration › Offres) doivent ensuite être synchronisées pour créer leurs produits et tarifs dans le compte Stripe choisi.</p>
            </div>
        </x-section>
        <x-section title="Connexion avec Google et Apple" class="lg:col-span-2">
            <p class="mb-3 text-sm text-slate-600">Les boutons « Continuer avec Google / Apple » apparaissent sur les pages de connexion et d'inscription dès qu'un fournisseur est entièrement renseigné.</p>
            <form method="POST" action="{{ route('admin.integrations.social') }}" class="grid gap-6 lg:grid-cols-2">
                @csrf @method('PUT')
                <div class="space-y-3">
                    <p class="flex items-center gap-2 font-medium">Google <x-badge :color="$social['google'] ? 'green' : 'amber'">{{ $social['google'] ? 'Actif' : 'Non configuré' }}</x-badge></p>
                    <x-field name="google_client_id" label="ID client OAuth" :value="$social['google_client_id']" placeholder="123456-abc.apps.googleusercontent.com" autocomplete="off" />
                    <x-field name="google_client_secret" type="password" label="Code secret du client" autocomplete="new-password"
                        :help="$masked['google.client_secret'] ? 'Enregistré ('.$masked['google.client_secret'].') — laisser vide pour le conserver.' : ($configured['google.client_secret'] ? 'Défini dans le .env.' : 'Non renseigné.')" />
                    <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                        <p>Google Cloud Console › API et services › Identifiants › « ID client OAuth » de type <strong>Application Web</strong>.</p>
                        <p class="mt-1">URI de redirection autorisé :</p>
                        <p class="mt-1 break-all rounded bg-white px-2 py-1 font-mono" x-data><span x-ref="u">{{ $social['google_callback'] }}</span> <button type="button" class="ml-1 link" @click="navigator.clipboard?.writeText($refs.u.textContent)">Copier</button></p>
                    </div>
                    @if ($social['google'])<x-checkbox name="clear[]" value="google" label="Désactiver Google (effacer les identifiants enregistrés)" />@endif
                </div>
                <div class="space-y-3">
                    <p class="flex items-center gap-2 font-medium">Apple <x-badge :color="$social['apple'] ? 'green' : 'amber'">{{ $social['apple'] ? 'Actif' : 'Non configuré' }}</x-badge></p>
                    <x-field name="apple_client_id" label="Services ID (identifiant)" :value="$social['apple_client_id']" placeholder="fr.nlapps.jackcie.web" autocomplete="off" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-field name="apple_team_id" label="Team ID" :value="$social['apple_team_id']" placeholder="ABCDE12345" autocomplete="off" />
                        <x-field name="apple_key_id" label="Key ID" :value="$social['apple_key_id']" placeholder="XYZ987WVU6" autocomplete="off" />
                    </div>
                    <x-textarea name="apple_private_key" label="Clé privée (contenu du fichier .p8)" rows="4" placeholder="-----BEGIN PRIVATE KEY-----"
                        :help="$masked['apple.private_key'] ? 'Enregistrée — laisser vide pour la conserver.' : ($configured['apple.private_key'] ? 'Définie dans le .env.' : 'Non renseignée.')" />
                    <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                        <p>Apple Developer › Certificates, Identifiers &amp; Profiles : un <strong>Services ID</strong> avec « Sign in with Apple » (domaine <code>{{ $social['domain'] }}</code>) et une <strong>clé</strong> « Sign in with Apple » (.p8).</p>
                        <p class="mt-1">Return URL :</p>
                        <p class="mt-1 break-all rounded bg-white px-2 py-1 font-mono" x-data><span x-ref="u">{{ $social['apple_callback'] }}</span> <button type="button" class="ml-1 link" @click="navigator.clipboard?.writeText($refs.u.textContent)">Copier</button></p>
                    </div>
                    @if ($social['apple'])<x-checkbox name="clear[]" value="apple" label="Désactiver Apple (effacer les identifiants enregistrés)" />@endif
                </div>
                <div class="lg:col-span-2"><button class="btn-primary">Enregistrer</button></div>
            </form>
        </x-section>
    </div>
</x-layouts.app>
