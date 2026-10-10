<html><head><meta charset="utf-8">@include('exports._style')</head><body>
<h1>Séances — {{ $horse->displayName() }}</h1>
<p class="muted">{{ $sessions->count() }} séance(s), {{ intdiv((int) $sessions->sum('actual_minutes'), 60) }} h {{ (int) $sessions->sum('actual_minutes') % 60 }} min réalisées</p>
<table><tr><th>Date</th><th>Type</th><th>Cavalier</th><th>Objectif</th><th>Exercices réalisés</th><th>Bilan</th></tr>
@foreach ($sessions as $s)<tr><td>{{ $s->scheduled_at->format('d/m/Y H:i') }}</td><td>{{ $s->typeLabel() }}<br><span class="small muted">{{ \App\Models\RidingSession::STATUSES[$s->status] }}{{ $s->actual_minutes ? ' · '.$s->actual_minutes.' min' : '' }}</span></td><td>{{ $s->riderLabel() }}</td><td>{{ $s->objective }}</td><td class="small">{{ $s->exercises->where('status', 'done')->pluck('name')->implode(', ') }}</td><td class="small">{{ $s->progress }}{{ $s->to_rework ? ' — À retravailler : '.$s->to_rework : '' }}</td></tr>@endforeach
</table>
</body></html>
