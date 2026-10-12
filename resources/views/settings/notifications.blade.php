<x-layouts.app title="Notifications">
    <x-page-header title="Paramètres" />
    @include('settings._nav')
    <div class="max-w-xl space-y-4">
        <div x-data="pushSettings({ vapidKey: @js($vapidKey), subscribeUrl: @js(route('push.subscribe')), unsubscribeUrl: @js(route('push.unsubscribe')), testUrl: @js(route('push.test')), stateUrl: @js(route('push.state')) })">
        <x-section title="Sur cet appareil">
            <p class="-mt-1 mb-4 text-sm text-slate-500">Recevez les rappels directement sur votre téléphone, même quand Jack&Cie est fermée.</p>

            <div x-show="state === 'checking'" class="text-sm text-slate-500">Vérification…</div>
            <div x-cloak x-show="state === 'install'" class="rounded-2xl bg-brand-50 p-4 text-sm text-brand-900">
                Sur iPhone et iPad, les notifications fonctionnent uniquement dans l'application installée (iOS 16.4 ou plus récent).
                <a href="{{ route('app.install') }}" class="mt-1 block font-semibold link">Installer Jack&Cie sur l'écran d'accueil</a>
            </div>
            <div x-cloak x-show="state === 'unsupported'" class="rounded-2xl bg-slate-100 p-4 text-sm text-slate-700">Ce navigateur ne permet pas les notifications. Utilisez l'application installée, Chrome, Edge, Firefox ou Safari récent.</div>
            <div x-cloak x-show="state === 'denied'" class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-900">Les notifications ont été refusées pour Jack&Cie. Autorisez-les dans les réglages du téléphone (Réglages › Notifications › Jack&Cie) ou du navigateur, puis revenez sur cette page.</div>

            <div x-cloak x-show="state === 'off'" class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn-primary" @click="enable()" :disabled="busy"><x-icon name="bell" class="size-4" /> Activer les notifications</button>
            </div>
            <div x-cloak x-show="state === 'on'" class="space-y-3">
                <p class="flex items-center gap-2 text-sm font-medium text-emerald-700"><x-icon name="check" class="size-5" /> Activées sur cet appareil</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary" @click="test()" :disabled="busy">Envoyer un essai</button>
                    <button type="button" class="btn-ghost" @click="disable()" :disabled="busy" x-show="! $store.install.handheld">Désactiver</button>
                </div>
            </div>
            <p x-cloak x-show="message" x-text="message" class="mt-3 text-sm text-slate-600" role="status"></p>
            <p x-cloak x-show="$store.install.handheld && state === 'on'" class="mt-3 text-xs text-slate-500">Sur téléphone, les notifications sont nécessaires au fonctionnement de Jack&Cie. Choisissez ci-dessous les rappels que vous souhaitez recevoir.</p>
            @if ($devices)<p class="mt-3 text-xs text-slate-400">{{ $devices }} appareil{{ $devices > 1 ? 's' : '' }} reçoi{{ $devices > 1 ? 'vent' : 't' }} vos notifications.</p>@endif
        </x-section>
        </div>

        @if (request('depuis') === 'rappel' && ! $profile->daily_reminder_at)
            <x-section class="border border-brand-100 bg-brand-50" data-daily-nudge>
                <p class="font-semibold text-brand-900">Un petit rappel chaque soir ?</p>
                <p class="mt-1 text-sm text-brand-900/80">Si vous n'avez rien noté dans la journée (séance, soin, observation…), Jack&amp;Cie vous le rappelle à l'heure choisie. Rien n'est envoyé les jours où vous avez déjà tout noté.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('settings.daily-reminder.enable') }}">@csrf<button class="btn-primary">Activer à 19 h</button></form>
                    <form method="POST" action="{{ route('settings.daily-reminder.stop') }}">@csrf<button class="btn-ghost">Ne plus me le proposer</button></form>
                </div>
            </x-section>
        @endif

        <form method="POST" action="{{ route('settings.notifications.update') }}" class="space-y-4" x-data="{ daily: {{ $profile->daily_reminder_at ? 'true' : 'false' }} }">
            @csrf @method('PUT')
            <x-section title="Rappels">
                <div class="space-y-4">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="debrief_reminders" value="1" @checked($profile->debrief_reminders ?? true) class="mt-0.5 size-5 rounded">
                        <span><span class="block font-medium text-slate-900">Après une séance ou un cours</span><span class="block text-sm text-slate-500">Une notification propose d'ajouter les exercices réalisés et le bilan.</span></span>
                    </label>
                    <div id="rappel-quotidien">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="daily_reminder" value="1" x-model="daily" class="mt-0.5 size-5 rounded">
                            <span><span class="block font-medium text-slate-900">Rappel quotidien</span><span class="block text-sm text-slate-500">Seulement si vous n'avez rien noté dans la journée.</span></span>
                        </label>
                        <div x-show="daily" x-cloak class="mt-3 ml-8 max-w-40">
                            <label class="label" for="daily_reminder_at">À</label>
                            <input id="daily_reminder_at" type="time" name="daily_reminder_at" step="900" value="{{ old('daily_reminder_at', $profile->daily_reminder_at ? substr($profile->daily_reminder_at, 0, 5) : '19:00') }}" class="input">
                            @error('daily_reminder_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @if ($profile->daily_reminder_at)<p class="mt-2 ml-8 text-xs {{ $dailyStatus['state'] === 'late' ? 'text-red-600' : 'text-slate-500' }}" data-daily-status>Aujourd'hui : {{ $dailyStatus['message'] }}</p>@endif
                    </div>
                    <label class="flex items-start gap-3 border-t border-slate-100 pt-4">
                        <input type="checkbox" name="email_notifications" value="1" @checked($profile->email_notifications ?? true) class="mt-0.5 size-5 rounded">
                        <span><span class="block font-medium text-slate-900">Recevoir aussi les notifications par email</span><span class="block text-sm text-slate-500">Rendez-vous, échéances de soins, invitations… (les rappels de saisie ne sont jamais envoyés par email).</span></span>
                    </label>
                </div>
            </x-section>
            <button class="btn-primary">Enregistrer</button>
        </form>
    </div>
</x-layouts.app>
