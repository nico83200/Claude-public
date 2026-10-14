{{-- Juste après l'inscription, sur téléphone ou tablette dans le navigateur : proposition d'installer l'application.
     Une seule fois : « Plus tard » ou l'installation la font disparaître (les rappels habituels restent dans le menu). --}}
@if (session('offer_install'))
<div x-data="{ open: false, later() { this.open = false; try { localStorage.setItem('jc-install-offer-seen', '1'); } catch (e) {} fetch(@js(route('install-offer.dismiss')), { method: 'POST', headers: { 'X-CSRF-TOKEN': @js(csrf_token()), Accept: 'application/json' } }).catch(() => {}); } }"
     x-init="let seen = false; try { seen = !! localStorage.getItem('jc-install-offer-seen'); } catch (e) {} open = $store.install.handheld && ! $store.install.installed && ! seen; $watch('$store.install.justInstalled', (v) => { if (v) later(); })"
     x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center bg-slate-900/45 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="install-offer-title" data-install-offer>
    <div class="w-full max-w-md rounded-t-[2rem] bg-white p-6 pb-[calc(1.5rem+env(safe-area-inset-bottom))] text-center shadow-2xl sm:rounded-[2rem]" @click.outside="later()">
        <img src="/icons/icon-192.png" alt="" class="mx-auto size-16 rounded-[22%] shadow-[var(--shadow-card)]">
        <p class="mt-3 text-xs font-semibold tracking-wide text-gold-600 uppercase">Votre compte est créé 🎉</p>
        <h2 id="install-offer-title" class="mt-1 text-xl font-bold text-slate-900">Installez Jack&amp;Cie sur votre <span x-text="/iPad|Tablet/.test(navigator.userAgent) || (navigator.maxTouchPoints > 1 && /Macintosh/.test(navigator.userAgent)) ? 'tablette' : 'téléphone'">téléphone</span></h2>
        <ul class="mx-auto mt-4 max-w-xs space-y-2 text-left text-sm text-slate-700">
            <li class="flex gap-2"><x-icon name="check" class="size-5 shrink-0 text-brand-600" /> Une icône sur l'écran d'accueil, en plein écran</li>
            <li class="flex gap-2"><x-icon name="check" class="size-5 shrink-0 text-brand-600" /> Les rappels de soins en notification</li>
            <li class="flex gap-2"><x-icon name="check" class="size-5 shrink-0 text-brand-600" /> Le micro pour dicter vos consignes</li>
        </ul>
        <button type="button" class="btn-primary mt-5 w-full text-base" @click="$store.install.install()" :disabled="$store.install.preparing" data-install-offer-install>
            <x-icon name="download" class="size-5" /> <span x-text="$store.install.preparing ? 'Préparation de l\'installation…' : 'Installer l\'application'">Installer l'application</span>
        </button>
        <p x-show="$store.install.isIOS" class="mt-3 text-xs text-slate-500">Sur iPhone et iPad, ouvrez ensuite Jack&amp;Cie depuis l'écran d'accueil et connectez-vous une fois, de la même façon qu'ici.</p>
        <p x-show="$store.install.justInstalled" class="mt-3 rounded-2xl bg-emerald-50 p-3 text-sm text-emerald-900">C'est installé : ouvrez Jack&amp;Cie depuis l'écran d'accueil.</p>
        <button type="button" class="btn-ghost mt-2 w-full text-sm" @click="later()" data-install-offer-later>Plus tard, continuer dans le navigateur</button>
    </div>
</div>
@endif
