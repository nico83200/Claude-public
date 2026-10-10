@php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; @endphp
<x-layouts.app :title="$org->name.' · Admin'">
    <x-page-header :title="$org->name" :subtitle="$org->typeLabel().' · '.$org->owner?->email.' · '.$horseCount.' cheval(aux)'" :back="route('admin.organizations.index')" />
    @include('admin._nav')
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4">
            <x-section title="Droits effectifs">
                <p class="text-sm"><strong>{{ $ent['plan']?->name ?? 'Aucune offre' }}</strong> — {{ \App\Models\License::STATUSES[$ent['status']] ?? 'Aucune licence' }} ({{ $ent['source'] ?? '—' }})</p>
                <p class="text-xs text-slate-500">Écriture : {{ $ent['writable'] ? 'oui' : 'non — '.$ent['read_only_reason'] }}</p>
                <p class="mt-1 text-xs">Limites : {{ collect($ent['limits'])->map(fn ($v, $k) => $k.' = '.($v ?? '∞'))->implode(', ') }}</p>
                <p class="text-xs">Fonctionnalités : {{ implode(', ', $ent['features']) ?: '—' }}</p>
            </x-section>
            <x-section title="Suspension">
                @if ($org->suspended_at)
                    <p class="mb-2 text-sm text-red-700">Suspendue le {{ $org->suspended_at->format('d/m/Y') }} : {{ $org->suspension_reason }}</p>
                    <form method="POST" action="{{ route('admin.organizations.reactivate', $org) }}">@csrf<button class="btn-primary w-full">Réactiver</button></form>
                @else
                    <form method="POST" action="{{ route('admin.organizations.suspend', $org) }}" class="space-y-2">@csrf<x-field name="reason" label="Motif" required /><button class="btn-danger w-full">Suspendre (lecture seule)</button></form>
                @endif
            </x-section>
            <x-section title="Membres">@foreach ($org->members as $m)<p class="text-sm"><a href="{{ route('admin.users.show', $m->user_id) }}" class="link">{{ $m->user->name }}</a> — {{ $m->role->name }}</p>@endforeach</x-section>
        </div>
        <div class="space-y-4 lg:col-span-2">
            <x-section title="Licences">
                @foreach ($licenses as $l)
                    <details class="border-t border-slate-100 py-2 text-sm first:border-0"><summary class="cursor-pointer"><strong>{{ $l->plan->name }}</strong> — {{ $l->statusLabel() }} · {{ $l->source }} · {{ $l->starts_at?->format('d/m/Y') }} → {{ $l->ends_at?->format('d/m/Y') ?? '…' }} {{ $l->grantor ? '· par '.$l->grantor->name : '' }}</summary>
                        @if ($l->notes)<p class="text-xs text-slate-500">{{ $l->notes }}</p>@endif
                        <form method="POST" action="{{ route('admin.licenses.update', $l) }}" class="mt-2 grid gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-2">
                            @csrf @method('PUT')
                            @if ($l->source !== 'stripe')
                                <x-select name="subscription_plan_id" label="Offre" :options="$plans" :value="$l->subscription_plan_id" />
                                <x-field name="starts_at" type="date" label="Début" :value="$l->starts_at?->format('Y-m-d')" />
                                <x-field name="ends_at" type="date" label="Fin" :value="$l->ends_at?->format('Y-m-d')" />
                                @foreach (['max_horses' => 'Chevaux max', 'max_members' => 'Membres max', 'max_invitations' => 'Partages max', 'storage_mb' => 'Stockage (Mo)'] as $k => $lab)<x-field :name="'limit_overrides['.$k.']'" type="number" :label="$lab.' (dérogation)'" :value="$l->limit_overrides[$k] ?? null" />@endforeach
                            @endif
                            <x-select name="status" label="État" :options="\App\Models\License::STATUSES" :value="$l->status" />
                            <x-textarea name="notes" label="Motif" :value="$l->notes" :required="$l->source !== 'stripe'" class="sm:col-span-2" />
                            <button class="btn-primary sm:col-span-2">Mettre à jour</button>
                        </form>
                    </details>
                @endforeach
                <details class="mt-3"><summary class="cursor-pointer text-sm link">Attribuer une licence manuelle (sans paiement Stripe)</summary>
                    <form method="POST" action="{{ route('admin.licenses.store', $org) }}" class="mt-2 grid gap-2 sm:grid-cols-2">
                        @csrf
                        <x-select name="subscription_plan_id" label="Offre" :options="$plans" required />
                        <x-select name="status" label="État" :options="\App\Models\License::STATUSES" value="active" />
                        <x-field name="starts_at" type="date" label="Début" :value="now()->toDateString()" />
                        <x-field name="ends_at" type="date" label="Fin (vide = sans fin)" />
                        @foreach (['max_horses' => 'Chevaux max', 'max_members' => 'Membres max', 'max_invitations' => 'Partages max', 'storage_mb' => 'Stockage (Mo)'] as $k => $lab)<x-field :name="'limit_overrides['.$k.']'" type="number" :label="$lab.' (dérogation)'" />@endforeach
                        <x-textarea name="notes" label="Motif (obligatoire)" required class="sm:col-span-2" />
                        <button class="btn-primary sm:col-span-2">Attribuer</button>
                    </form>
                </details>
            </x-section>
            <x-section title="Abonnements Stripe">
                @forelse ($subscriptions as $s)<p class="border-t border-slate-100 py-1.5 text-sm first:border-0">{{ $s->plan->name }} · {{ $s->stripe_status }} · {{ $eur($s->unit_amount) }}/{{ $s->interval === 'year' ? 'an' : 'mois' }} · <span class="font-mono text-xs">{{ $s->stripe_subscription_id }}</span> {{ $s->cancel_at_period_end ? '· résiliation programmée' : '' }}</p>@empty<p class="text-sm text-slate-500">Aucun.</p>@endforelse
            </x-section>
            <x-section title="Paiements">
                @forelse ($payments as $p)<p class="border-t border-slate-100 py-1.5 text-sm first:border-0">{{ $p->created_at->format('d/m/Y') }} · {{ $p->status }} · {{ $eur($p->amount_paid ?: $p->amount_due) }} {{ $p->amount_refunded > 0 ? '(remboursé '.$eur($p->amount_refunded).')' : '' }} {{ $p->failure_message }}</p>@empty<p class="text-sm text-slate-500">Aucun.</p>@endforelse
            </x-section>
        </div>
    </div>
</x-layouts.app>
