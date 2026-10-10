<x-layouts.app title="Facturation · Admin">
    <x-page-header title="Facturation et webhooks" />
    @include('admin._nav')
    <x-section title="Événements Stripe">
        <form class="mb-3 flex flex-wrap gap-2"><x-select name="status" :options="['received' => 'Reçus', 'processed' => 'Traités', 'ignored' => 'Ignorés', 'failed' => 'En échec']" :value="request('status')" placeholder="Tous" aria-label="Statut" /><input name="type" value="{{ request('type') }}" placeholder="Type (ex. invoice)" class="input max-w-xs" aria-label="Type"><button class="btn-secondary">Filtrer</button></form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Reçu</th><th>Type</th><th>Statut</th><th>Tentatives</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($events as $e)
                <tr><td class="text-xs">{{ $e->created_at->format('d/m H:i:s') }}<span class="block font-mono text-[10px] text-slate-400">{{ $e->stripe_event_id }}</span></td><td class="text-sm">{{ $e->type }}</td>
                    <td><x-badge :color="['processed' => 'green', 'failed' => 'red', 'ignored' => 'slate'][$e->status] ?? 'amber'">{{ $e->status }}</x-badge>@if ($e->last_error)<span class="block text-xs text-red-600">{{ \Illuminate\Support\Str::limit($e->last_error, 120) }}</span>@endif</td>
                    <td>{{ $e->attempts }}</td>
                    <td>@if ($e->status === 'failed')<form method="POST" action="{{ route('admin.billing.reprocess', $e) }}">@csrf<button class="btn-secondary text-xs">Retraiter</button></form>@endif</td></tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="mt-3">{{ $events->links() }}</div>
    </x-section>
    <x-section title="Derniers paiements" class="mt-4">
        <form class="mb-3"><x-select name="payment_status" :options="['paid' => 'Payés', 'failed' => 'Échoués', 'refunded' => 'Remboursés', 'open' => 'Ouverts']" :value="request('payment_status')" placeholder="Tous" onchange="this.form.submit()" aria-label="Statut" /></form>
        @foreach ($payments as $p)<p class="border-t border-slate-100 py-1.5 text-sm first:border-0">{{ $p->created_at->format('d/m/Y') }} · <a href="{{ route('admin.organizations.show', $p->organization_id) }}" class="link">{{ $p->organization?->name }}</a> · {{ $p->status }} · {{ number_format((float) ($p->amount_paid ?: $p->amount_due), 2, ',', ' ') }} {{ $p->currency }} {{ $p->number }}</p>@endforeach
    </x-section>
</x-layouts.app>
