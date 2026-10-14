@props(['purpose' => 'vous connecter'])
{{-- Sur téléphone ou tablette, dans le navigateur : seule l'installation de l'application est proposée.
     Le contenu (connexion, inscription) s'affiche dans l'application installée ou sur ordinateur. --}}
@php $handheld = \App\Support\Device::isHandheld(request()); @endphp
<div x-data>
    <div x-show="$store.install.gate" @unless ($handheld) style="display: none" @endunless class="text-center">
        <img src="/icons/icon-192.png" alt="" class="mx-auto size-20 rounded-[22%] shadow-[var(--shadow-card)]">
        <h1 class="mt-5 text-2xl font-bold">Installez l'application</h1>
        <p class="mt-2 text-[15px] text-slate-600">Sur téléphone et tablette, Jack&amp;Cie s'utilise depuis son application : plein écran, accès direct, micro pour dicter vos consignes. Installez-la, puis ouvrez-la pour {{ $purpose }}.</p>
        <template x-if="$store.install.inApp">
            <div class="mt-6 rounded-2xl bg-amber-50 p-4 text-left text-sm text-amber-900">
                Vous êtes dans le navigateur d'une autre application, qui ne permet pas l'installation. Ouvrez cette page dans <strong x-text="$store.install.isIOS ? 'Safari' : 'Chrome'"></strong> (menu ⋯ › « Ouvrir dans le navigateur »).
                <button type="button" class="btn-secondary mt-3 w-full" x-data="{ copied: false }" @click="navigator.clipboard?.writeText(location.href); copied = true" x-text="copied ? 'Lien copié' : 'Copier le lien'"></button>
            </div>
        </template>
        <template x-if="! $store.install.inApp">
            <div class="mt-6">
                <div x-show="$store.install.justInstalled" class="rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900">
                    <strong>Jack&amp;Cie est installée.</strong> Ouvrez-la depuis l'écran d'accueil ou le tiroir d'applications de votre téléphone pour {{ $purpose }}.
                </div>
                <button x-show="! $store.install.justInstalled" type="button" class="btn-primary w-full text-base" @click="$store.install.install()" :disabled="$store.install.preparing">
                    <x-icon name="download" class="size-5" /> <span x-text="$store.install.preparing ? 'Préparation de l\'installation…' : 'Installer Jack&Cie'">Installer Jack&amp;Cie</span>
                </button>
                @if ($purpose === 'vous connecter')
                    <p class="mt-5 rounded-2xl bg-slate-50 p-3 text-sm text-slate-600">Pas encore de compte ? <a href="{{ route('register', request()->only('code', 'src', 'parrain')) }}" class="font-semibold link" data-gate-register>Créez-le ici</a>, l'installation vous sera proposée ensuite.</p>
                @endif
                <p class="mt-4 text-sm text-slate-500">Déjà installée ? Ouvrez <strong>Jack&amp;Cie</strong> depuis l'écran d'accueil de votre {{ '' }}<span x-text="$store.install.isIOS && /iPad/.test(navigator.userAgent) ? 'tablette' : 'téléphone'"></span>.</p>
            </div>
        </template>
    </div>
    <div x-show="! $store.install.gate" @if ($handheld) style="display: none" @endif>
        {{ $slot }}
    </div>
</div>
