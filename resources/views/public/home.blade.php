<x-layouts.public title="Gestion équine simple, pour un cheval comme pour une écurie">
    <section class="mx-auto max-w-5xl px-4 py-14 text-center sm:py-20">
        <p class="mb-3 inline-block rounded-full bg-brand-100 px-3 py-1 text-xs font-semibold text-brand-800">Utilisable hors ligne dans l'écurie</p>
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">Le carnet de vie de vos chevaux,<br class="hidden sm:block"> partagé avec les bonnes personnes.</h1>
        <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-600">Identité, santé, soins, séances, exercices, alimentation, demi-pensions et dépenses. Pour le propriétaire d'un seul cheval comme pour l'écurie qui en gère des dizaines.</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('register') }}" class="btn-primary px-6 text-base">Créer un compte gratuit</a>
            <a href="{{ route('pricing') }}" class="btn-secondary px-6 text-base">Voir les offres</a>
        </div>
    </section>
    <section class="mx-auto grid max-w-5xl gap-4 px-4 pb-16 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['health', 'Santé et soins', 'Dossier sanitaire chronologique, traitements, rappels de vaccins, vermifuges et maréchalerie.'],
            ['session', 'Séances et progression', 'Préparez vos séances, cochez les exercices sur le téléphone, faites le bilan et suivez la progression.'],
            ['share', 'Demi-pension et partage', 'Donnez exactement les droits souhaités, pour la durée voulue, et révoquez en un clic.'],
            ['building', 'Écuries', 'Membres, rôles, chevaux en pension : chacun voit ce qu\'il doit voir, sans duplication de dossier.'],
            ['offline', 'Hors ligne', 'Les données utiles restent disponibles sans réseau et se synchronisent au retour de la connexion.'],
            ['wallet', 'Budget', 'Dépenses par cheval et par catégorie, exports CSV et PDF.'],
        ] as [$icon, $title, $text])
            <div class="card p-5"><div class="mb-3 inline-flex rounded-lg bg-brand-50 p-2 text-brand-700"><x-icon :name="$icon" class="size-6" /></div><h2 class="font-semibold">{{ $title }}</h2><p class="mt-1 text-sm text-slate-600">{{ $text }}</p></div>
        @endforeach
    </section>
    @include('public._plans')
</x-layouts.public>
