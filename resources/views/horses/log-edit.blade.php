<x-layouts.app :title="'Modifier le suivi · '.$horse->shortName()">
    <x-horse-header :horse="$horse" />
    <div class="mx-auto max-w-xl">
        <x-section title="Modifier la saisie">
            <form method="POST" action="{{ route('horses.logs.update', [$horse, $log]) }}" class="space-y-3">
                @csrf @method('PUT')
                @include('horses._log-form')
                <div class="flex gap-2"><a href="{{ route('horses.logs.index', $horse) }}" class="btn-secondary flex-1 justify-center">Annuler</a><button class="btn-primary flex-1 justify-center">Enregistrer</button></div>
            </form>
            <form method="POST" action="{{ route('horses.logs.destroy', [$horse, $log]) }}" class="mt-4 border-t border-slate-100 pt-4" data-confirm="Supprimer cette saisie du suivi ?">
                @csrf @method('DELETE')
                <button class="btn-ghost w-full justify-center text-red-700"><x-icon name="trash" class="size-4" /> Supprimer la saisie</button>
            </form>
        </x-section>
    </div>
</x-layouts.app>
