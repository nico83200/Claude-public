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
            @if ($expenses->isNotEmpty())
                <x-section title="Informations financières" data-care-finance>
                    @foreach ($expenses as $e)
                        <div class="border-t border-slate-100 py-2 text-sm first:border-0 first:pt-0">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="font-medium">{{ $e->category->name }}</span>
                                <span class="font-semibold">{{ number_format((float) $e->amount, 2, ',', ' ') }} €</span>
                            </div>
                            <p class="text-xs text-slate-500">{{ $e->spent_on->format('d/m/Y') }}{{ $e->supplier ? ' · '.$e->supplier : '' }}</p>
                            <p class="mt-1">@if ($e->isPaid())<x-badge color="green">Payée{{ $e->paid_on ? ' le '.$e->paid_on->format('d/m/Y') : '' }}</x-badge>@else<x-badge :color="$e->isOverdue() ? 'red' : 'amber'">À payer</x-badge>@endif</p>
                            @if ($e->comment)<p class="mt-1 text-xs text-slate-600">{{ $e->comment }}</p>@endif
                            <p class="mt-1 flex flex-wrap gap-3 text-xs">
                                @foreach ($e->documents as $d)<a href="{{ route('budget.receipt', [$e, $d]) }}" class="link" @if ($d->isImage()) data-lightbox="care-receipt-{{ $e->id }}" data-caption="Justificatif · {{ $e->category->name }}" @endif>Justificatif</a>@endforeach
                                @if ($canEditExpense($e))<a href="{{ route('budget.edit', ['expense' => $e, 'retour' => '/'.request()->path()]) }}" class="link font-medium" data-care-expense-edit>Modifier la dépense</a>@endif
                                <a href="{{ route('budget.index', ['horse' => $horse->id, 'year' => $e->spent_on->year]) }}" class="link">Voir dans le budget</a>
                            </p>
                        </div>
                    @endforeach
                </x-section>
            @endif
            <x-section title="Documents">
                @forelse ($record->documents as $d)<a href="{{ route('horses.documents.download', [$horse, $d]) }}" class="block py-1 text-sm link" @if ($d->isImage()) data-lightbox="care-docs" data-caption="{{ $d->title }}" @endif>{{ $d->title }}</a>@empty<p class="text-sm text-slate-500">Aucun document joint.</p>@endforelse
            </x-section>
            @if ($record->treatments->isNotEmpty())
                <x-section title="Traitements associés">@foreach ($record->treatments as $t)<p class="text-sm">{{ $t->product }} — {{ $t->dosage }}</p>@endforeach</x-section>
            @endif
            <p class="text-xs text-slate-500">Dernière modification : {{ $record->updated_at->format('d/m/Y H:i') }} (version {{ $record->version }})</p>
        </div>
    </div>
</x-layouts.app>
