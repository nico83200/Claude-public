<x-layouts.public title="Toute la vie de votre cheval, réunie">
    <section class="relative overflow-hidden">
        <div class="mx-auto max-w-5xl px-4 pt-16 pb-14 text-center sm:pt-24">
            <x-brand-mark class="mx-auto w-64 sm:w-80" />
            <h1 class="sr-only">Jack&amp;Cie — Toute la vie de votre cheval, réunie.</h1>
            <p class="mx-auto mt-8 max-w-2xl text-lg text-slate-600">Santé, soins, séances, alimentation, demi-pensions et dépenses, réunis avec soin. Pour un seul cheval comme pour toute une écurie — même là où le réseau ne passe pas.</p>
            <div class="mt-9 flex flex-wrap justify-center gap-3">
                <a href="{{ route('register') }}" class="btn-primary px-7 text-base">Commencer gratuitement</a>
                <a href="{{ route('app.install') }}" class="btn-secondary px-6 text-base"><x-icon name="download" class="size-4" /> Installer sur mon téléphone</a>
            </div>
        </div>
    </section>

    <section class="mx-4 grid max-w-5xl gap-px overflow-hidden rounded-3xl lg:mx-auto border border-sand-200 bg-sand-200 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['health', 'Santé et soins', 'Un dossier sanitaire clair : vaccins, vermifuges, maréchalerie, traitements et rappels.'],
            ['session', 'Séances et progression', 'Préparez, cochez les exercices au bord de la carrière, faites le bilan, mesurez les progrès.'],
            ['share', 'Demi-pension sereine', 'Chacun voit exactement ce qu\'il doit voir, pour la durée choisie. Révocable à tout instant.'],
            ['building', 'Écuries', 'Membres, rôles et chevaux en pension, sans jamais dupliquer un dossier.'],
            ['offline', 'Même sans réseau', 'Le mode écurie garde l\'essentiel sur le téléphone et se synchronise au retour du signal.'],
            ['wallet', 'Budget maîtrisé', 'Dépenses par cheval et par poste, exports en un geste.'],
        ] as [$icon, $title, $text])
            <div class="bg-white p-7"><div class="mb-4 inline-flex rounded-full bg-gold-50 p-2.5 text-brand-800 ring-1 ring-gold-200"><x-icon :name="$icon" class="size-5" /></div><h2 class="text-lg">{{ $title }}</h2><p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ $text }}</p></div>
        @endforeach
    </section>

    <section class="mx-auto mt-16 max-w-5xl px-4">
        <div class="flex flex-col items-center gap-6 rounded-3xl bg-brand-900 px-6 py-10 text-center text-sand-50 sm:flex-row sm:px-10 sm:text-left">
            <img src="/icons/icon-192.png" alt="" class="size-20 rounded-[22%] ring-1 ring-gold-300/40">
            <div class="flex-1">
                <h2 class="text-2xl text-sand-50">Une vraie app, sans passer par un store</h2>
                <p class="mt-1 text-sm text-gold-200">Ajoutez Jack&amp;Cie à l'écran d'accueil de votre iPhone ou Android : plein écran, accès instantané, fonctionnement hors ligne.</p>
            </div>
            <a href="{{ route('app.install') }}" class="btn bg-sand-50 text-brand-900 hover:bg-white">Voir comment</a>
        </div>
    </section>

    @include('public._plans')
</x-layouts.public>
