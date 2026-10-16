<x-layouts.app title="Organisations · Admin">
    <x-page-header title="Organisations" />
    @include('admin._nav')
    @if ($duplicates->isNotEmpty())
        <x-section class="mb-5 border border-amber-200 bg-amber-50/60" data-duplicate-stables>
            <h2 class="text-[17px] font-semibold text-amber-950">Comptes avec plusieurs écuries ({{ $duplicates->count() }})</h2>
            <p class="mt-1 text-sm text-amber-900/80">Un compte ne peut gérer qu'une écurie. Choisissez celle qui reste : les autres sont suspendues (lecture seule, données et chevaux conservés, réactivables depuis leur fiche). Les abonnements payants ne sont pas résiliés automatiquement.</p>
            <div class="mt-4 space-y-4">
                @foreach ($duplicates as $d)
                    <form method="POST" action="{{ route('admin.organizations.keep-stable', $d['owner']) }}" class="rounded-2xl bg-white p-4 ring-1 ring-amber-100">
                        @csrf
                        <p class="font-semibold">{{ $d['owner']->name }} <span class="font-normal text-slate-500">· {{ $d['owner']->email }}</span></p>
                        <div class="mt-3 space-y-2">
                            @foreach ($d['stables'] as $i => $o)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="keep_id" value="{{ $o->id }}" @checked($i === 0) class="mt-0.5 size-5">
                                    <span class="min-w-0 flex-1"><a href="{{ route('admin.organizations.show', $o) }}" class="font-medium link">{{ $o->name }}</a>
                                        <span class="block text-xs text-slate-500">Créée le {{ $o->created_at->format('d/m/Y') }} · {{ \App\Support\Fr::count($o->horses_count, 'cheval', 'chevaux') }} · {{ \App\Support\Fr::count($o->members_count, 'membre', 'membres') }}</span></span>
                                    @if ($o->paid)<x-badge color="amber">Abonnement payant en cours</x-badge>@endif
                                </label>
                            @endforeach
                        </div>
                        <button class="btn-primary mt-3" data-confirm="Garder l'écurie sélectionnée et suspendre les autres écuries de ce compte ?" data-confirm-ok="Garder et suspendre les autres">Garder l'écurie sélectionnée</button>
                    </form>
                @endforeach
            </div>
        </x-section>
    @endif
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
