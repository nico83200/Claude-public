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
        <x-section title="Surveillance" data-monitor-settings>
            <div class="space-y-3">
                <label class="flex items-start gap-2 text-sm"><input type="hidden" name="monitor_enabled" value="0"><input type="checkbox" name="monitor_enabled" value="1" class="mt-1" @checked($settings['monitor_enabled'] ?? true)> <span>Alerter les super-administrateurs par e-mail et notification <span class="block text-xs text-slate-500">Cron arrêté, erreurs en hausse. Une alerte au plus toutes les 6 heures, et un message quand le cron repart.</span></span></label>
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="monitor_cron_minutes" type="number" min="5" max="1440" label="Cron silencieux depuis (min)" :value="$settings['monitor_cron_minutes'] ?: 15" />
                    <x-field name="monitor_error_threshold" type="number" min="1" max="10000" label="Erreurs par heure (seuil)" :value="$settings['monitor_error_threshold'] ?: 20" />
                </div>
                @php $silent = \App\Services\SystemMonitor::cronSilentMinutes(); @endphp
                <p class="text-xs text-slate-500" data-monitor-status>État actuel : cron {{ $silent === null ? 'jamais vu' : ($silent < 3 ? 'actif' : 'silencieux depuis '.$silent.' min') }} · {{ \App\Services\SystemMonitor::errorsLastHour() }} erreur(s) sur la dernière heure.</p>
            </div>
        </x-section>
        <x-section title="Notifications">
            <div class="space-y-3">
                <x-field name="inactivity_hours" type="number" min="0" max="720" label="Rappel après une absence (heures)" :value="$settings['inactivity_hours'] ?? 0"
                    help="Notification envoyée une fois quand un utilisateur n'a pas ouvert l'application depuis ce nombre d'heures (entre 9 h et 20 h). 0 = désactivé." />
                <x-field name="inactivity_message" label="Texte de la notification" maxlength="200" :value="$settings['inactivity_message']"
                    :placeholder="\App\Console\Commands\SendActivityReminders::DEFAULT_INACTIVITY_MESSAGE" />
            </div>
        </x-section>
        <x-section title="Fonctionnalités activables">
            @foreach ($flags as $flag)<x-checkbox :name="'flags['.$flag->key.']'" :label="$flag->label" :checked="$flag->enabled_globally" />@endforeach
            <p class="mt-2 text-xs text-slate-500">Email et Stripe : <a href="{{ route('admin.integrations.edit') }}" class="link">Intégrations</a>. Autres paramètres techniques (stockage) : variables d'environnement, voir <code>.env.example</code>.</p>
            <dl class="mt-3 grid grid-cols-2 gap-1 text-xs text-slate-600">
                <dt>Email</dt><dd>{{ config('mail.default') }}</dd>
                <dt>Stripe</dt><dd>{{ config('services.stripe.secret') ? 'configuré' : 'non configuré' }} / webhook {{ config('services.stripe.webhook_secret') ? 'configuré' : 'non configuré' }}</dd>
                <dt>Taille max. des fichiers</dt><dd>{{ config('equine.uploads.max_kb') }} Ko</dd>
                <dt>Grâce / récupération</dt><dd>{{ config('equine.billing.grace_days') }} j / {{ config('equine.billing.recovery_days') }} j</dd>
            </dl>
        </x-section>
        <div class="lg:col-span-2"><button class="btn-primary">Enregistrer</button></div>
    </form>
</x-layouts.app>
