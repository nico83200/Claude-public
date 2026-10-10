<x-layouts.app title="Offres · Admin">
    <x-page-header title="Offres commerciales"><a href="{{ route('admin.plans.create') }}" class="btn-primary"><x-icon name="plus" /> Nouvelle offre</a></x-page-header>
    @include('admin._nav')
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Offre</th><th>Prix</th><th>Limites</th><th>Abonnés actifs</th><th>État</th><th></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @foreach ($plans as $p)
            <tr class="{{ $p->archived_at ? 'opacity-60' : '' }}">
                <td><span class="font-medium">{{ $p->name }}</span><span class="block text-xs text-slate-500">{{ $p->slug }} · {{ ['individual' => 'Particuliers', 'stable' => 'Écuries', 'any' => 'Tous'][$p->audience] }}</span></td>
                <td class="text-sm">{{ $p->price_monthly !== null ? number_format((float) $p->price_monthly, 2, ',', ' ').' €/mois' : '—' }}<br>{{ $p->price_yearly !== null ? number_format((float) $p->price_yearly, 2, ',', ' ').' €/an' : '' }}
                    @if (! $p->is_default_free && (($p->price_monthly && ! $p->stripe_price_monthly_id) || ($p->price_yearly && ! $p->stripe_price_yearly_id)))<span class="block text-xs text-amber-700">Prix Stripe manquant</span>@endif</td>
                <td class="text-xs">{{ $p->max_horses ?? '∞' }} chevaux · {{ $p->max_members ?? '∞' }} membres · {{ $p->storage_mb ? $p->storage_mb.' Mo' : '∞' }}</td>
                <td>{{ $p->active_subscriptions_count }}</td>
                <td>@if ($p->archived_at)<x-badge>Archivée</x-badge>@elseif ($p->is_active)<x-badge color="green">Active</x-badge>@else<x-badge color="amber">Inactive</x-badge>@endif @if ($p->is_default_free)<x-badge color="blue">Gratuite par défaut</x-badge>@endif @unless ($p->is_public)<x-badge>Privée</x-badge>@endunless</td>
                <td><a href="{{ route('admin.plans.edit', $p) }}" class="link">Modifier</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</x-layouts.app>
