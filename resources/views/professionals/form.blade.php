<x-layouts.app :title="$professional->exists ? 'Modifier l\'intervenant' : 'Nouvel intervenant'">
    <x-page-header :title="$professional->exists ? $professional->fullName() : 'Nouvel intervenant'" :back="$professional->exists ? route('professionals.show', $professional) : route('professionals.index')" />
    <form method="POST" action="{{ $professional->exists ? route('professionals.update', $professional) : route('professionals.store') }}" class="space-y-4">
        @csrf @if ($professional->exists) @method('PUT') @endif
        <x-section>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="kind" label="Fonction" :options="\App\Models\Professional::KINDS" :value="$professional->kind" required />
                <x-field name="company" label="Cabinet / société" :value="$professional->company" />
                <x-field name="first_name" label="Prénom" :value="$professional->first_name" />
                <x-field name="last_name" label="Nom" :value="$professional->last_name" required />
                <x-field name="phone" type="tel" label="Téléphone" :value="$professional->phone" />
                <x-field name="email" type="email" label="Email" :value="$professional->email" />
                <x-field name="address" label="Adresse" :value="$professional->address" class="sm:col-span-2" />
                <x-field name="usual_rate" type="number" step="0.01" min="0" label="Tarif habituel (€, facultatif)" :value="$professional->usual_rate" />
                <x-textarea name="notes" label="Notes" :value="$professional->notes" class="sm:col-span-2" />
            </div>
            @if (session('duplicate_id'))<div class="mt-3"><x-checkbox name="force" label="Créer quand même (personne différente)" /></div>@endif
        </x-section>
        <button class="btn-primary">Enregistrer</button>
    </form>
</x-layouts.app>
