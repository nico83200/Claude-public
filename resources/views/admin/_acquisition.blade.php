{{-- Provenance des inscriptions : scans de QR code et clics sur les liens suivis (?src=…). --}}
@php $pct = fn ($a, $b) => \App\Http\Controllers\Admin\StatsController::pct((int) $a, (int) $b); @endphp
@if ($rows->isEmpty())
    <p class="text-sm text-slate-500">Aucune arrivée par un lien suivi sur la période. Créez vos liens et QR codes avec le <a href="{{ route('admin.promo-codes.index') }}" class="link">générateur de QR code</a> (Codes promo) en choisissant une source : chaque scan ou clic sera compté ici.</p>
@else
    <div class="table-wrap" data-acquisition><table class="table">
        <thead><tr><th>Provenance</th><th class="text-right">Scans / clics</th><th class="text-right">Visiteurs</th><th class="text-right">Comptes créés</th><th class="text-right">Codes activés</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @foreach ($rows as $r)
            <tr>
                <td><span class="font-medium">{{ $r->label }}</span>@if ($r->code)<span class="block text-xs text-slate-500">Code {{ $r->code }}</span>@endif</td>
                <td class="text-right tabular-nums">{{ $r->hits }}</td>
                <td class="text-right tabular-nums">{{ $r->visitors }}</td>
                <td class="text-right tabular-nums">{{ $r->accounts }} <span class="block text-xs text-slate-500">{{ $pct($r->accounts, $r->visitors) }}</span></td>
                <td class="text-right tabular-nums">{{ $r->redeemed }}</td>
            </tr>
        @endforeach
        </tbody>
        @if ($rows->count() > 1)
            <tfoot><tr class="font-semibold"><td>Total</td><td class="text-right tabular-nums">{{ $rows->sum('hits') }}</td><td class="text-right tabular-nums">{{ $rows->sum('visitors') }}</td><td class="text-right tabular-nums">{{ $rows->sum('accounts') }}</td><td class="text-right tabular-nums">{{ $rows->sum('redeemed') }}</td></tr></tfoot>
        @endif
    </table></div>
    <p class="mt-2 text-xs text-slate-500">Scans / clics : chaque ouverture du lien ou du QR code. Visiteurs : appareils différents. Comptes créés : inscriptions faites dans la foulée (email, Google ou Apple), avec le taux de conversion. Aucune donnée personnelle n'est enregistrée.</p>
@endif
