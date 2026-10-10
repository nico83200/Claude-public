<html><head><meta charset="utf-8">@include('exports._style')</head><body>
<h1>{{ $horse->displayName() }}</h1>
<p class="muted">{{ collect([$horse->breed?->name, \App\Models\Horse::SEXES[$horse->sex] ?? null, $horse->age() !== null ? $horse->age().' ans' : null, $horse->coat])->filter()->implode(' · ') }}</p>
<h2>Identité</h2>
<table>
@foreach (['N° SIRE' => $horse->identifier('sire'), 'UELN' => $horse->identifier('ueln'), 'Transpondeur' => $horse->identifier('transponder'), 'Date de naissance' => $horse->birth_date?->format('d/m/Y') ?? $horse->birth_year, 'Taille' => $horse->height_cm ? $horse->height_cm.' cm' : null, 'Pays de naissance' => $horse->birth_country, 'Éleveur' => $horse->breeder, 'Discipline' => \App\Models\Horse::DISCIPLINES[$horse->main_discipline] ?? null, 'Niveau' => $horse->work_level, 'Lieu de vie' => $horse->current_location] as $l => $v)
    @if ($v)<tr><th style="width:30%">{{ $l }}</th><td>{{ $v }}</td></tr>@endif
@endforeach
</table>
@if ($horse->pedigree->isNotEmpty())
<h2>Généalogie</h2>
<table>@foreach ($horse->pedigree as $r)<tr><th style="width:30%">{{ \App\Models\HorseRelationship::RELATIONS[$r->relation] }}</th><td>{{ $r->related_name }} {{ $r->related_breed ? '('.$r->related_breed.')' : '' }}</td></tr>@endforeach</table>
@endif
<h2>Propriétaires et détenteurs</h2>
<table>@foreach ($horse->ownerships as $o)<tr><td>{{ $o->displayName() }}</td><td>{{ ['owner' => 'Propriétaire', 'co_owner' => 'Copropriétaire', 'keeper' => 'Détenteur'][$o->role] }}</td><td class="muted">{{ $o->started_on?->format('d/m/Y') }} → {{ $o->ended_on?->format('d/m/Y') ?? 'en cours' }}</td></tr>@endforeach</table>
@if ($horse->particularities || $horse->precautions || $horse->care_instructions)
<h2>Particularités et consignes</h2>
@if ($horse->particularities)<p><strong>Particularités :</strong> {!! nl2br(e($horse->particularities)) !!}</p>@endif
@if ($horse->precautions)<p><strong>Précautions :</strong> {!! nl2br(e($horse->precautions)) !!}</p>@endif
@if ($horse->care_instructions)<p><strong>Consignes de soins :</strong> {!! nl2br(e($horse->care_instructions)) !!}</p>@endif
@endif
@if ($treatments->isNotEmpty())
<h2>Traitements en cours</h2>
<table><tr><th>Produit</th><th>Posologie</th><th>Fréquence</th><th>Fin</th></tr>@foreach ($treatments as $t)<tr><td>{{ $t->product }}</td><td>{{ $t->dosage }}</td><td>{{ $t->frequency }}</td><td>{{ $t->ends_on?->format('d/m/Y') }}</td></tr>@endforeach</table>
@endif
@if ($feeding)
<h2>Alimentation — {{ $feeding->name }}</h2>
<table><tr><th>Heure</th><th>Aliment</th><th>Quantité</th></tr>@foreach ($feeding->entries as $e)<tr><td>{{ $e->time_of_day ? substr($e->time_of_day, 0, 5) : '' }}</td><td>{{ $e->feed }}</td><td>{{ $e->quantity ? rtrim(rtrim($e->quantity, '0'), '.').' '.$e->unit : '' }}</td></tr>@endforeach</table>
@endif
</body></html>
