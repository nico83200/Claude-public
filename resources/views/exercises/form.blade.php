<x-layouts.app :title="$exercise->exists ? 'Modifier l\'exercice' : 'Nouvel exercice'">
    <x-page-header :title="$exercise->exists ? 'Modifier : '.$exercise->name : 'Nouvel exercice'" :back="$exercise->exists ? route('exercises.show', $exercise) : route('exercises.index')" />
    @if ($exercise->exists)<p class="mb-4 rounded-lg bg-sky-50 p-3 text-sm text-sky-900">Les séances déjà réalisées conservent la version de l'exercice utilisée : vos modifications ne s'appliqueront qu'aux prochaines séances.</p>@endif
    <form method="POST" action="{{ $exercise->exists ? route('exercises.update', $exercise) : route('exercises.store') }}" class="space-y-4">
        @csrf @if ($exercise->exists) @method('PUT') @endif
        <x-section>
            @include('exercises._fields')
            @if ($canShare && ! $exercise->exists)<div class="mt-3"><x-checkbox name="share" label="Partager dans l'écurie (visible par tous les membres)" /></div>@endif
        </x-section>
        <button class="btn-primary">Enregistrer</button>
    </form>
</x-layouts.app>
