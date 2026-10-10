<x-layouts.app :title="$horse->exists ? 'Modifier '.$horse->shortName() : 'Nouveau cheval'">
    <x-page-header :title="$horse->exists ? 'Modifier la fiche' : 'Nouveau cheval'" :subtitle="'Espace : '.$org->name" :back="$horse->exists ? route('horses.show', $horse) : route('horses.index')" />
    @if ($limitReached)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">La limite de chevaux de votre offre est atteinte. <a href="{{ route('billing.index') }}" class="link">Changer d'offre</a> ou archiver un cheval.</div>
    @endif
    @unless ($horse->exists)
        <p class="mb-4 text-sm text-slate-600">Astuce : <a href="{{ route('horse-search.form') }}" class="link">recherchez votre cheval sur Internet</a> pour pré-remplir la fiche à partir de sources publiques.</p>
    @endunless
    <form method="POST" action="{{ $horse->exists ? route('horses.update', $horse) : route('horses.store') }}" class="space-y-5">
        @csrf @if ($horse->exists) @method('PUT') @endif
        <x-section title="Identité">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="official_name" label="Nom officiel" :value="$horse->official_name" required maxlength="150" />
                <x-field name="usual_name" label="Nom d'usage" :value="$horse->usual_name" maxlength="100" />
                <x-select name="horse_breed_id" label="Race" :options="$breeds" :value="$horse->horse_breed_id" placeholder="—" />
                <x-select name="sex" label="Sexe" :options="\App\Models\Horse::SEXES" :value="$horse->sex" required />
                <x-field name="birth_date" type="date" label="Date de naissance" :value="$horse->birth_date?->format('Y-m-d')" />
                <x-field name="birth_year" type="number" label="ou année de naissance" :value="$horse->birth_year" min="1950" :max="now()->year" />
                <x-field name="coat" label="Robe" :value="$horse->coat" maxlength="80" />
                <x-field name="height_cm" type="number" label="Taille au garrot (cm)" :value="$horse->height_cm" min="50" max="220" />
                <x-field name="birth_country" label="Pays de naissance (code ISO, ex. FR)" :value="$horse->birth_country" maxlength="2" />
                <x-field name="breeder" label="Éleveur" :value="$horse->breeder" maxlength="150" />
            </div>
        </x-section>
        <x-section title="Identifiants">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="identifiers[sire]" label="N° SIRE" :value="$horse->identifier('sire')" help="8 chiffres et une lettre" />
                <x-field name="identifiers[ueln]" label="UELN" :value="$horse->identifier('ueln')" help="15 caractères" />
                <x-field name="identifiers[transponder]" label="Transpondeur" :value="$horse->identifier('transponder')" inputmode="numeric" />
                <x-field name="identifiers[passport]" label="N° de document d'identification" :value="$horse->identifier('passport')" />
            </div>
        </x-section>
        <x-section title="Travail et particularités">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="main_discipline" label="Discipline principale" :options="\App\Models\Horse::DISCIPLINES" :value="$horse->main_discipline" placeholder="—" />
                <x-field name="work_level" label="Niveau de travail" :value="$horse->work_level" maxlength="60" />
                <x-field name="current_location" label="Lieu de vie actuel" :value="$horse->current_location" maxlength="150" class="sm:col-span-2" />
                <x-textarea name="particularities" label="Particularités" :value="$horse->particularities" class="sm:col-span-2" />
                <x-textarea name="precautions" label="Précautions (visibles des cavaliers autorisés)" :value="$horse->precautions" class="sm:col-span-2" />
                <x-textarea name="care_instructions" label="Consignes de soins" :value="$horse->care_instructions" class="sm:col-span-2" />
                <x-textarea name="general_notes" label="Observations générales" :value="$horse->general_notes" rows="4" class="sm:col-span-2" />
            </div>
        </x-section>
        @unless ($horse->exists)
            <x-section><x-checkbox name="i_am_owner" label="Je suis propriétaire de ce cheval" :checked="$org->isPersonal()" help="Vous serez enregistré comme propriétaire (modifiable ensuite)." /></x-section>
        @endunless
        <div class="flex gap-2"><button class="btn-primary" @disabled($limitReached)>Enregistrer</button><a href="{{ $horse->exists ? route('horses.show', $horse) : route('horses.index') }}" class="btn-ghost">Annuler</a></div>
    </form>
</x-layouts.app>
