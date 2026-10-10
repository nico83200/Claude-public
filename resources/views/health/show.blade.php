<x-layouts.app :title="$record->category->name.' · '.$horse->shortName()">
    <x-page-header :title="$record->category->name.($record->reason ? ' — '.$record->reason : '')" :subtitle="$horse->shortName().' · '.$record->performed_at->format('d/m/Y H:i')" :back="route('horses.care.index', $horse)">
        @if ($canEdit)
            <a href="{{ route('horses.care.edit', [$horse, $record]) }}" class="btn-secondary">Modifier</a>
            <x-confirm-delete :action="route('horses.care.destroy', [$horse, $record])" message="Supprimer ce soin ?" />
        @endif
    </x-page-header>
    <div class="grid gap-4 lg:grid-cols-3">
        <x-section class="lg:col-span-2">
            <x-dl :items="[
                'Professionnel' => $record->professional?->fullName(),
                'Coût' => $record->cost ? number_format((float) $record->cost, 2, ',', ' ').' €' : null,
                'Prochain contrôle' => $record->next_check_on?->format('d/m/Y'),
                'Saisi par' => $record->author?->name,
                'Observations' => $record->observations,
                'Soins réalisés' => $record->care_performed,
                'Diagnostic communiqué' => $record->diagnosis,
                'Consignes' => $record->instructions,
            ]" />
        </x-section>
        <div class="space-y-4">
            <x-section title="Documents">
                @forelse ($record->documents as $d)<a href="{{ route('horses.documents.download', [$horse, $d]) }}" class="block py-1 text-sm link">{{ $d->title }}</a>@empty<p class="text-sm text-slate-500">Aucun document joint.</p>@endforelse
            </x-section>
            @if ($record->treatments->isNotEmpty())
                <x-section title="Traitements associés">@foreach ($record->treatments as $t)<p class="text-sm">{{ $t->product }} — {{ $t->dosage }}</p>@endforeach</x-section>
            @endif
            <p class="text-xs text-slate-500">Dernière modification : {{ $record->updated_at->format('d/m/Y H:i') }} (version {{ $record->version }})</p>
        </div>
    </div>
</x-layouts.app>
