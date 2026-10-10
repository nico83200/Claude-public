{{-- Invitation à installer Jack&Cie sur le téléphone + feuille d'instructions iPhone --}}
<div x-data x-cloak>
    <div x-show="$store.install.showBanner" x-transition.opacity
         class="fixed inset-x-3 {{ $bannerBottom ?? 'bottom-[calc(4.5rem+env(safe-area-inset-bottom))]' }} z-30 mx-auto max-w-md rounded-2xl border border-sand-200 bg-white/95 p-3 shadow-lg backdrop-blur lg:bottom-6"
         role="dialog" aria-label="Installer l'application">
        <div class="flex items-center gap-3">
            <img src="/icons/icon-192.png" alt="" class="size-11 rounded-[22%]">
            <div class="min-w-0 flex-1">
                <p class="font-display text-[15px] leading-tight font-semibold">Jack&amp;Cie sur votre téléphone</p>
                <p class="text-xs text-slate-500">Plein écran, accès direct, utilisable à l'écurie sans réseau.</p>
            </div>
            <button type="button" @click="$store.install.dismiss()" class="rounded-lg p-1.5 text-slate-400 hover:text-slate-700" aria-label="Plus tard"><x-icon name="x" class="size-4" /></button>
        </div>
        <button type="button" @click="$store.install.install()" class="btn-primary mt-3 w-full">Installer l'application</button>
    </div>

    {{-- Instructions (iPhone / iPad, ou navigateur sans invite native) --}}
    <div x-show="$store.install.sheet" x-transition.opacity class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 sm:items-center" @click.self="$store.install.sheet = false" @keydown.escape.window="$store.install.sheet = false">
        <div class="w-full max-w-md rounded-t-3xl bg-white p-6 pb-[calc(1.5rem+env(safe-area-inset-bottom))] shadow-xl sm:rounded-3xl" role="dialog" aria-modal="true" aria-labelledby="install-title">
            <div class="mb-4 flex items-center gap-3">
                <img src="/icons/icon-192.png" alt="" class="size-12 rounded-[22%]">
                <div><p id="install-title" class="font-display text-lg font-semibold">Installer Jack&amp;Cie</p><p class="text-xs text-slate-500">Moins d'une minute, sans passer par un store.</p></div>
            </div>
            <template x-if="$store.install.isIOS">
                <ol class="space-y-3 text-sm">
                    <template x-if="! $store.install.isIOSSafari"><li class="rounded-xl bg-amber-50 p-3 text-amber-900">Ouvrez cette page dans <strong>Safari</strong> : l'installation sur iPhone se fait depuis Safari.</li></template>
                    <li class="flex items-center gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800">1</span><span>Touchez <strong>Partager</strong>
                        <svg class="mx-1 inline size-5 align-text-bottom text-sky-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-label="icône Partager"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0-12L8 7m4-4l4 4M6 11H5a1 1 0 00-1 1v8a1 1 0 001 1h14a1 1 0 001-1v-8a1 1 0 00-1-1h-1"/></svg>
                        en bas de l'écran (ou en haut sur iPad).</span></li>
                    <li class="flex items-center gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800">2</span><span>Faites défiler et choisissez <strong>Sur l'écran d'accueil</strong> <span class="inline-flex size-5 items-center justify-center rounded border border-slate-400 align-text-bottom text-xs">+</span>.</span></li>
                    <li class="flex items-center gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800">3</span><span>Touchez <strong>Ajouter</strong> : l'icône Jack&amp;Cie apparaît avec vos autres apps.</span></li>
                </ol>
            </template>
            <template x-if="! $store.install.isIOS">
                <ol class="space-y-3 text-sm">
                    <li class="flex items-center gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800">1</span><span>Ouvrez le menu du navigateur <strong>⋮</strong> (Chrome, Edge, Samsung Internet).</span></li>
                    <li class="flex items-center gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800">2</span><span>Choisissez <strong>Installer l'application</strong> ou <strong>Ajouter à l'écran d'accueil</strong>.</span></li>
                    <li class="flex items-center gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800">3</span><span>Confirmez : Jack&amp;Cie s'ouvre ensuite en plein écran depuis son icône.</span></li>
                </ol>
            </template>
            <button type="button" @click="$store.install.sheet = false; $store.install.dismiss()" class="btn-secondary mt-6 w-full">J'ai compris</button>
        </div>
    </div>
</div>
