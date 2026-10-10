@php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; @endphp
<x-layouts.app title="Administration">
    <x-page-header title="Administration" subtitle="Espace super-administrateur" />
    @include('admin._nav')
    @if ($failedEvents)<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">{{ $failedEvents }} webhook(s) Stripe en échec. <a href="{{ route('admin.billing.index', ['status' => 'failed']) }}" class="link">Voir</a></div>@endif
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (['users' => 'Comptes', 'organizations' => 'Organisations', 'stables' => 'Écuries', 'horses' => 'Chevaux', 'active_subscriptions' => 'Abonnements actifs', 'trialing' => 'En essai', 'manual_licenses' => 'Licences manuelles'] as $k => $l)
            <div class="card p-4"><p class="text-xs text-slate-500">{{ $l }}</p><p class="text-2xl font-bold">{{ number_format($counts[$k], 0, ',', ' ') }}</p></div>
        @endforeach
        <div class="card p-4"><p class="text-xs text-slate-500">Résiliations (30 j) / programmées</p><p class="text-2xl font-bold">{{ $cancellations }} / {{ $scheduledCancellations }}</p></div>
    </div>
    <div class="mb-4 grid gap-3 lg:grid-cols-3">
        <div class="card p-4"><p class="text-xs text-slate-500">Revenu récurrent mensuel <strong>estimé</strong></p><p class="text-2xl font-bold">{{ $eur($mrrEstimate) }}</p><p class="text-xs text-slate-500">Projection à partir des abonnements actifs (hors essais). Pas un encaissement.</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Facturé ce mois-ci</p><p class="text-2xl font-bold">{{ $eur($billedThisMonth) }}</p><p class="text-xs text-slate-500">Montant des factures émises par Stripe.</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Encaissé ce mois-ci (net des remboursements)</p><p class="text-2xl font-bold">{{ $eur($collectedThisMonth) }}</p><p class="text-xs text-slate-500">Paiements confirmés par Stripe.</p></div>
    </div>
    <div class="grid gap-4 lg:grid-cols-3">
        <x-section title="Répartition par offre">@forelse ($byPlan as $name => $c)<div class="flex justify-between text-sm"><span>{{ $name }}</span><span class="font-medium">{{ $c }}</span></div>@empty<p class="text-sm text-slate-500">—</p>@endforelse</x-section>
        <x-section title="Nouvelles souscriptions (12 mois)">
            @php $max = max(1, $signups->max() ?? 1); @endphp
            <div class="flex h-32 items-end gap-1">@foreach ($signups as $m => $c)<div class="flex flex-1 flex-col items-center gap-1" title="{{ $m }} : {{ $c }}"><div class="w-full rounded-t bg-slate-700" style="height: {{ max(3, round($c / $max * 100)) }}px"></div><span class="text-[9px] text-slate-500">{{ substr($m, 5) }}</span></div>@endforeach</div>
        </x-section>
        <x-section title="Paiements échoués (30 j)">
            @forelse ($failedPayments as $p)<a href="{{ route('admin.organizations.show', $p->organization_id) }}" class="block border-t border-slate-100 py-1.5 text-sm first:border-0">{{ $p->organization?->name }} — {{ $eur($p->amount_due) }}<span class="block text-xs text-red-600">{{ $p->failure_message }}</span></a>@empty<p class="text-sm text-slate-500">Aucun.</p>@endforelse
        </x-section>
    </div>
</x-layouts.app>
