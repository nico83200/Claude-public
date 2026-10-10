<x-layouts.app :title="'Généalogie · '.$horse->shortName()">
    <x-page-header :title="$horse->shortName()" subtitle="Généalogie" :back="route('horses.show', $horse)" />
    @include('horses._tabs')
    @php
        $node = function ($rel, $label) use ($pedigree, $linkable) {
            $r = $pedigree->get($rel);
            $name = $r?->related_name ?? $r?->related?->official_name;
            return ['label' => $label, 'name' => $name, 'breed' => $r?->related_breed, 'url' => in_array($rel, $linkable) ? route('horses.pedigree', $r->related_horse_id) : null, 'ref' => $r?->external_reference];
        };
        $tree = [
            [$node('sire', 'Père'), [$node('sire_sire', 'Grand-père paternel'), $node('sire_dam', 'Grand-mère paternelle')]],
            [$node('dam', 'Mère'), [$node('dam_sire', 'Grand-père maternel'), $node('dam_dam', 'Grand-mère maternelle')]],
        ];
    @endphp
    <x-section title="Arbre généalogique">
        <div class="overflow-x-auto">
            <div class="grid min-w-[560px] grid-cols-3 items-center gap-3">
                <div class="row-span-2 rounded-xl border-2 border-brand-500 bg-brand-50 p-3 text-center"><p class="text-xs text-slate-500">Cheval</p><p class="font-bold">{{ $horse->official_name }}</p><p class="text-xs">{{ $horse->breed?->name }}</p></div>
                @foreach ($tree as $i => [$parent, $grands])
                    <div class="col-start-2 rounded-xl border border-slate-200 bg-white p-3 text-center {{ $i === 0 ? 'row-start-1' : 'row-start-2' }}">
                        <p class="text-xs text-slate-500">{{ $parent['label'] }}</p>
                        @if ($parent['url'])<a href="{{ $parent['url'] }}" class="font-semibold link">{{ $parent['name'] }}</a>@else<p class="font-semibold">{{ $parent['name'] ?? 'Inconnu' }}</p>@endif
                        @if ($parent['breed'])<p class="text-xs">{{ $parent['breed'] }}</p>@endif
                        @if ($parent['ref'] && str_starts_with($parent['ref'], 'http'))<a href="{{ $parent['ref'] }}" target="_blank" rel="noopener" class="text-xs link">source</a>@endif
                    </div>
                    <div class="col-start-3 space-y-2 {{ $i === 0 ? 'row-start-1' : 'row-start-2' }}">
                        @foreach ($grands as $g)
                            <div class="rounded-lg border border-slate-200 bg-white p-2 text-center text-sm"><p class="text-[11px] text-slate-500">{{ $g['label'] }}</p>@if ($g['url'])<a href="{{ $g['url'] }}" class="font-medium link">{{ $g['name'] }}</a>@else<p class="font-medium">{{ $g['name'] ?? 'Inconnu' }}</p>@endif</div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        @if ($horse->origin)
            <div class="mt-4"><x-dl :items="['Stud-book' => $horse->origin->studbook, 'Lignée' => $horse->origin->bloodline, 'Notes' => $horse->origin->notes]" /></div>
        @endif
    </x-section>

    @if ($canEdit)
    <x-section title="Modifier la généalogie" class="mt-4">
        <form method="POST" action="{{ route('horses.pedigree.update', $horse) }}" class="space-y-4">
            @csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="studbook" label="Stud-book" :value="$horse->origin?->studbook" />
                <x-field name="bloodline" label="Lignée" :value="$horse->origin?->bloodline" />
            </div>
            @foreach (\App\Models\HorseRelationship::RELATIONS as $rel => $label)
                @php $r = $pedigree->get($rel); @endphp
                <fieldset class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-4">
                    <legend class="px-1 text-sm font-semibold">{{ $label }}</legend>
                    <x-field :name="'rel['.$rel.'][name]'" label="Nom" :value="$r?->related_name" />
                    <x-field :name="'rel['.$rel.'][breed]'" label="Race" :value="$r?->related_breed" />
                    <x-select :name="'rel['.$rel.'][horse_id]'" label="Lier à une fiche" :options="$candidates" :value="$r?->related_horse_id" placeholder="—" />
                    <x-field :name="'rel['.$rel.'][reference]'" label="Référence / lien" :value="$r?->external_reference" />
                </fieldset>
            @endforeach
            <x-textarea name="origin_notes" label="Notes sur les origines" :value="$horse->origin?->notes" />
            <button class="btn-primary">Enregistrer</button>
        </form>
    </x-section>
    @endif
</x-layouts.app>
