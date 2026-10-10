<x-layouts.public title="Installer l'application">
    <section class="mx-auto max-w-3xl px-4 pt-12 pb-6 text-center">
        <img src="/icons/icon-512.png" alt="Icône Jack&Cie" class="mx-auto mb-6 size-24 rounded-[22%] shadow-md ring-1 ring-sand-200">
        <h1 class="text-3xl sm:text-4xl">Jack&amp;Cie, directement sur votre téléphone</h1>
        <p class="mx-auto mt-3 max-w-xl text-slate-600">Une icône sur l'écran d'accueil, une ouverture en plein écran, et le mode écurie qui fonctionne même sans réseau. Aucun store, aucun téléchargement lourd.</p>
        <div x-data class="mt-6">
            <p x-show="$store.install.installed" class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-800"><x-icon name="check" class="size-4" /> L'application est déjà installée sur cet appareil.</p>
            <button x-show="! $store.install.installed" type="button" @click="$store.install.install()" class="btn-primary px-6 text-base"><x-icon name="download" /> Installer maintenant</button>
        </div>
    </section>

    <section class="mx-auto grid max-w-5xl gap-4 px-4 py-8 md:grid-cols-3">
        <div class="card p-6">
            <p class="mb-1 text-xs font-semibold tracking-widest text-gold-500 uppercase">iPhone · iPad</p>
            <h2 class="mb-3 text-lg">Avec Safari</h2>
            <ol class="list-decimal space-y-2 pl-5 text-sm text-slate-700">
                <li>Ouvrez <strong>{{ url('/') }}</strong> dans Safari.</li>
                <li>Touchez <strong>Partager</strong> (carré avec une flèche vers le haut).</li>
                <li>Choisissez <strong>Sur l'écran d'accueil</strong>, puis <strong>Ajouter</strong>.</li>
            </ol>
            <p class="mt-3 text-xs text-slate-500">iOS 16.4 ou plus récent recommandé.</p>
        </div>
        <div class="card p-6">
            <p class="mb-1 text-xs font-semibold tracking-widest text-gold-500 uppercase">Android</p>
            <h2 class="mb-3 text-lg">Avec Chrome</h2>
            <ol class="list-decimal space-y-2 pl-5 text-sm text-slate-700">
                <li>Touchez <strong>Installer l'application</strong> sur cette page ou dans le bandeau proposé.</li>
                <li>Sinon : menu <strong>⋮</strong> &gt; <strong>Installer l'application</strong> (ou « Ajouter à l'écran d'accueil »).</li>
                <li>Confirmez : l'icône apparaît dans vos applications.</li>
            </ol>
        </div>
        <div class="card p-6">
            <p class="mb-1 text-xs font-semibold tracking-widest text-gold-500 uppercase">Ordinateur</p>
            <h2 class="mb-3 text-lg">Chrome ou Edge</h2>
            <ol class="list-decimal space-y-2 pl-5 text-sm text-slate-700">
                <li>Cliquez sur l'icône d'installation dans la barre d'adresse.</li>
                <li>Ou menu &gt; <strong>Installer Jack&amp;Cie</strong>.</li>
            </ol>
        </div>
    </section>

    <section class="mx-auto max-w-3xl px-4 pb-16">
        <div class="card p-6 text-sm text-slate-700">
            <h2 class="mb-2 text-lg">Bon à savoir</h2>
            <ul class="list-disc space-y-1.5 pl-5">
                <li>Pour travailler sans réseau, ouvrez une fois le <strong>mode écurie</strong> connecté, après avoir rendu vos chevaux « disponibles hors ligne ».</li>
                <li>Les mises à jour sont automatiques : rien à réinstaller.</li>
                <li>Pour désinstaller, supprimez l'icône comme n'importe quelle application ; vos données restent sur votre compte.</li>
            </ul>
        </div>
    </section>
</x-layouts.public>
