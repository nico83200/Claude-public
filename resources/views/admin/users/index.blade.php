<x-layouts.app title="Utilisateurs · Admin">
    <x-page-header title="Utilisateurs" />
    @include('admin._nav')
    <form class="mb-4 flex flex-wrap gap-2"><input name="q" value="{{ request('q') }}" placeholder="Nom ou email" class="input max-w-xs" aria-label="Rechercher"><x-select name="filter" :options="['suspended' => 'Suspendus', 'admins' => 'Super-admins', 'unverified' => 'Email non vérifié', 'deletion' => 'Suppression demandée']" :value="request('filter')" placeholder="Tous" aria-label="Filtre" /><button class="btn-secondary">Filtrer</button></form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Utilisateur</th><th>Espaces</th><th>Inscription</th><th>État</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @foreach ($users as $u)
            <tr><td><a href="{{ route('admin.users.show', $u) }}" class="link">{{ $u->name }}</a><span class="block text-xs text-slate-500">{{ $u->email }}</span></td>
                <td class="text-xs">{{ $u->memberships->map(fn ($m) => $m->organization?->name)->filter()->implode(', ') }}</td>
                <td class="text-xs">{{ $u->created_at->format('d/m/Y') }}</td>
                <td>@if ($u->trashed())<x-badge>Supprimé</x-badge>@elseif ($u->suspended_at)<x-badge color="red">Suspendu</x-badge>@else<x-badge color="green">Actif</x-badge>@endif @if ($u->is_super_admin)<x-badge color="purple">Admin</x-badge>@endif</td></tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="mt-3">{{ $users->links() }}</div>
</x-layouts.app>
