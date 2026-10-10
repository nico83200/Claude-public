<html><head><meta charset="utf-8">@include('exports._style')</head><body>
<h1>{{ $title }}</h1>
<p class="muted">{{ $expenses->count() }} dépense(s) — total {{ number_format((float) $expenses->sum('amount'), 2, ',', ' ') }} €</p>
<table><tr><th>Date</th><th>Cheval</th><th>Catégorie</th><th>Fournisseur</th><th>Commentaire</th><th class="right">Montant</th></tr>
@foreach ($expenses as $e)<tr><td>{{ $e->spent_on->format('d/m/Y') }}</td><td>{{ $e->horse?->official_name }}</td><td>{{ $e->category?->name }}</td><td>{{ $e->supplier }}</td><td class="small">{{ $e->comment }}</td><td class="right">{{ number_format((float) $e->amount, 2, ',', ' ') }} €</td></tr>@endforeach
</table>
</body></html>
