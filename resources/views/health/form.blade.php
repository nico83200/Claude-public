<x-layouts.app :title="$record->exists ? 'Modifier le soin' : 'Nouveau soin'">
    <x-page-header :title="$record->exists ? 'Modifier le soin' : 'Nouveau soin'" :subtitle="$horse->shortName()" :back="route('horses.care.index', $horse)" />
    <form method="POST" action="{{ $record->exists ? route('horses.care.update', [$horse, $record]) : route('horses.care.store', $horse) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf @if ($record->exists) @method('PUT') <input type="hidden" name="version" value="{{ $record->version }}"> @endif
        <x-section>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="care_category_id" label="Catégorie" :options="$categories" :value="$record->care_category_id" required placeholder="Choisir…" />
                <x-field name="performed_at" type="datetime-local" label="Date et heure" :value="$record->performed_at?->format('Y-m-d\TH:i')" required />
                <x-select name="professional_id" label="Professionnel" :options="$professionals" :value="$record->professional_id" placeholder="—" help="Absent de la liste ? Ajoutez-le dans Intervenants." />
                <x-field name="reason" label="Motif" :value="$record->reason" />
                <x-textarea name="observations" label="Observations" :value="$record->observations" />
                <x-textarea name="care_performed" label="Soins réalisés" :value="$record->care_performed" />
                <x-textarea name="diagnosis" label="Diagnostic communiqué par le professionnel" :value="$record->diagnosis" />
                <x-textarea name="instructions" label="Consignes" :value="$record->instructions" />
                <x-field name="cost" type="number" step="0.01" min="0" label="Coût (€)" :value="$record->cost" />
                <x-field name="next_check_on" type="date" label="Prochain contrôle" :value="$record->next_check_on?->format('Y-m-d')" help="Crée automatiquement un rappel dans le calendrier." />
            </div>
        </x-section>
        <x-section>
            @if ($canExpense)<x-checkbox name="add_expense" label="Ajouter le coût aux dépenses du cheval" :checked="! $record->exists" />@endif
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <div><label class="label" for="att">Joindre un document (compte rendu, ordonnance, facture)</label><input id="att" type="file" name="attachment" class="text-sm">@error('attachment')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <x-select name="attachment_category" label="Type de document" :options="$docCategories" value="report" />
            </div>
        </x-section>
        <button class="btn-primary">Enregistrer</button>
    </form>
</x-layouts.app>
