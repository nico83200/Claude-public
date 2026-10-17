@php $o = $statement->organization; $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; @endphp
<html><head><meta charset="utf-8">@include('exports._style')</head><body>
<table style="border:0;margin-bottom:18px"><tr>
    <td style="border:0;vertical-align:top"><strong style="font-size:15px">{{ $o->name }}</strong><br>
        <span class="small">{{ $o->address_line }}@if ($o->postal_code || $o->city)<br>{{ trim($o->postal_code.' '.$o->city) }}@endif @if ($o->email)<br>{{ $o->email }}@endif @if ($o->phone)<br>{{ $o->phone }}@endif</span></td>
    <td style="border:0;vertical-align:top;text-align:right"><strong>{{ $statement->title() }}</strong><br>N° {{ $statement->number }}<br>{{ $statement->periodLabel() }}<br><span class="small">Émis le {{ ($statement->sent_at ?? $statement->created_at)->format('d/m/Y') }}</span></td>
</tr></table>
<p><strong>À l'attention de :</strong> {{ $statement->owner_name }}@if ($statement->owner_email) <span class="muted">({{ $statement->owner_email }})</span>@endif</p>
<table>
    <tr><th>Cheval</th><th>Désignation</th><th>Détail</th><th class="right">Qté</th><th class="right">Prix</th><th class="right">Montant</th></tr>
    @foreach ($statement->lines as $l)
        <tr><td>{{ $l['horse'] }}</td><td>{{ $l['label'] }}</td><td class="small">{{ $l['detail'] }}</td><td class="right">{{ rtrim(rtrim(number_format((float) $l['quantity'], 2, ',', ''), '0'), ',') }}</td><td class="right">{{ $eur($l['unit_price']) }}</td><td class="right">{{ $eur($l['amount']) }}</td></tr>
    @endforeach
    <tr><th colspan="5" class="right">Total</th><th class="right">{{ $eur($statement->total) }}</th></tr>
</table>
<p class="small muted" style="margin-top:14px">
    @if ($statement->status === 'paid')Réglé le {{ $statement->paid_on->format('d/m/Y') }}. Merci !@else À régler à réception.@endif
    Relevé établi avec Jack&amp;Cie. Montants indiqués toutes taxes comprises, selon les conditions convenues avec l'écurie.
</p>
</body></html>
