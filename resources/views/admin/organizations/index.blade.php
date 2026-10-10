<x-layouts.app title="Organisations · Admin">
    <x-page-header title="Organisations" />
    @include('admin._nav')
    <form class="mb-4 flex flex-wrap gap-2"><input name="q" value="{{ request('q') }}" placeholder="Nom ou code" class="input max-w-xs" aria-label="Rechercher"><x-select name="type" :options="['personal' => 'Personnel', 'stable' => 'Écurie']" :value="request('type')" placeholder="Tous types" aria-label="Type" /><x-select name="filter" :options="['suspended' => 'Suspendues']" :value="request('filter')" placeholder="Toutes" aria-label="Filtre" /><button class="btn-secondary">Filtrer</button></form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Organisation</th><th>Propriétaire</th><th>Chevaux</th><th>Membres</th><th>État</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @foreach ($organizations as $o)
            <tr><td><a href="{{ route('admin.organizations.show', $o) }}" class="link">{{ $o->name }}</a><span class="block text-xs text-slate-500">{{ $o->typeLabel() }} · {{ $o->slug }}</span></td><td class="text-xs">{{ $o->owner?->email }}</td><td>{{ $o->horses_count }}</td><td>{{ $o->members_count }}</td><td>@if ($o->suspended_at)<x-badge color="red">Suspendue</x-badge>@else<x-badge color="green">Active</x-badge>@endif @if ($o->is_demo)<x-badge color="amber">Démo</x-badge>@endif</td></tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="mt-3">{{ $organizations->links() }}</div>
</x-layouts.app>
