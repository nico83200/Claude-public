<html><head><meta charset="utf-8">@include('exports._style')</head><body>
<h1>Historique sanitaire — {{ $horse->displayName() }}</h1>
<p class="muted">{{ $horse->breed?->name }} · SIRE {{ $horse->identifier('sire') ?? '—' }}</p>
<h2>Soins et interventions</h2>
<table><tr><th>Date</th><th>Catégorie</th><th>Professionnel</th><th>Motif / soins</th><th>Prochain contrôle</th></tr>
@forelse ($records as $r)<tr><td>{{ $r->performed_at->format('d/m/Y') }}</td><td>{{ $r->category->name }}</td><td>{{ $r->professional?->fullName() }}</td><td>{{ $r->reason }}@if ($r->care_performed)<br><span class="small">{{ $r->care_performed }}</span>@endif @if ($r->diagnosis)<br><span class="small"><em>Diagnostic communiqué :</em> {{ $r->diagnosis }}</span>@endif</td><td>{{ $r->next_check_on?->format('d/m/Y') }}</td></tr>
@empty<tr><td colspan="5" class="muted">Aucun soin enregistré.</td></tr>@endforelse
</table>
<h2>Traitements</h2>
<table><tr><th>Produit</th><th>Posologie prescrite</th><th>Période</th><th>Statut</th></tr>
@forelse ($treatments as $t)<tr><td>{{ $t->product }}</td><td>{{ $t->dosage }} {{ $t->frequency }}</td><td>{{ $t->starts_on->format('d/m/Y') }} → {{ $t->ends_on?->format('d/m/Y') }}</td><td>{{ \App\Models\Treatment::STATUSES[$t->status] }}</td></tr>
@empty<tr><td colspan="4" class="muted">Aucun traitement.</td></tr>@endforelse
</table>
</body></html>
