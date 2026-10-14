{{-- Activation obligatoire des notifications sur téléphone/tablette (application installée). --}}
@php
    $pushConfig = [
        'vapidKey' => rescue(fn () => app(\App\Services\WebPush::class)->publicKey(), null, false),
        'subscribeUrl' => route('push.subscribe'),
        'stateUrl' => route('push.state'),
        // Pages toujours accessibles : export et suppression des données personnelles.
        'enabled' => ! request()->routeIs('settings.privacy', 'settings.export', 'settings.data-request'),
    ];
@endphp
<script type="application/json" id="push-config">@json($pushConfig)</script>
<div id="push-gate" x-data x-cloak x-show="$store.pushGate.show" x-transition.opacity
     class="fixed inset-0 z-[100] flex flex-col overflow-y-auto bg-white px-6 pt-[max(3rem,env(safe-area-inset-top))] pb-[max(2rem,env(safe-area-inset-bottom))]"
     role="dialog" aria-modal="true" aria-labelledby="push-gate-title">
    <div class="m-auto w-full max-w-sm text-center">
        <div class="relative mx-auto mb-8 flex size-24 items-center justify-center rounded-[2rem] bg-brand-50 text-brand-800">
            <x-icon name="bell" class="size-11" />
            <span class="absolute -top-1 -right-1 flex size-7 items-center justify-center rounded-full bg-brand-800 text-sm font-bold text-white ring-4 ring-white">1</span>
        </div>

        <template x-if="$store.pushGate.state === 'ask'">
            <div>
                <h1 id="push-gate-title" class="text-[26px] leading-tight font-bold">Activez les notifications</h1>
                <p class="mt-3 text-[15px] text-slate-600">Jack&Cie vous prévient au bon moment : bilan après un cours, rendez-vous du maréchal, vaccin à faire, paiement de la pension…</p>
                <ul class="mt-6 space-y-3 text-left text-sm text-slate-700">
                    <li class="flex gap-3"><x-icon name="check" class="size-5 shrink-0 text-brand-700" /> Uniquement des rappels utiles à vos chevaux</li>
                    <li class="flex gap-3"><x-icon name="check" class="size-5 shrink-0 text-brand-700" /> Rappels réglables dans Paramètres › Notifications</li>
                    <li class="flex gap-3"><x-icon name="check" class="size-5 shrink-0 text-brand-700" /> Jamais de publicité</li>
                </ul>
                <button type="button" class="btn-primary big-touch mt-8 w-full" @click="$store.pushGate.enable()" :disabled="$store.pushGate.busy">
                    <span x-text="$store.pushGate.busy ? 'Activation…' : 'Activer les notifications'">Activer les notifications</span>
                </button>
                <p class="mt-3 text-xs text-slate-400">Votre téléphone va vous demander l'autorisation : choisissez « Autoriser ».</p>
            </div>
        </template>

        <template x-if="$store.pushGate.state === 'denied'">
            <div>
                <h1 class="text-[26px] leading-tight font-bold">Notifications bloquées</h1>
                <p class="mt-3 text-[15px] text-slate-600">Elles ont été refusées pour Jack&Cie. Pour continuer, autorisez-les dans les réglages du téléphone :</p>
                <ol x-show="$store.install.isIOS" class="mt-5 space-y-2 rounded-3xl bg-slate-50 p-5 text-left text-sm text-slate-700">
                    <li>1. Ouvrez l'app <strong>Réglages</strong></li><li>2. Touchez <strong>Notifications</strong>, puis <strong>Jack&Cie</strong></li><li>3. Activez <strong>Autoriser les notifications</strong></li>
                </ol>
                <ol x-show="! $store.install.isIOS" class="mt-5 space-y-2 rounded-3xl bg-slate-50 p-5 text-left text-sm text-slate-700">
                    <li>1. Appuyez longuement sur l'icône <strong>Jack&Cie</strong> de l'écran d'accueil</li><li>2. Touchez <strong>Infos sur l'appli</strong> (ⓘ), puis <strong>Notifications</strong></li><li>3. Activez <strong>Autoriser les notifications</strong></li>
                </ol>
                <button type="button" class="btn-primary big-touch mt-6 w-full" @click="$store.pushGate.check().then(() => { if ($store.pushGate.state === 'ask') $store.pushGate.enable() })">C'est fait, continuer</button>
            </div>
        </template>

        <p x-show="$store.pushGate.error" x-text="$store.pushGate.error" class="mt-4 text-sm text-red-600" role="alert"></p>
        <form method="POST" action="{{ route('logout') }}" class="mt-8">@csrf<input type="hidden" name="pwa" data-standalone><button class="text-sm text-slate-400 underline-offset-4 hover:underline">Se déconnecter</button></form>
    </div>
</div>
