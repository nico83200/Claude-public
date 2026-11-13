<x-layouts.app title="Journal · Admin">
    <x-page-header title="Historique des actions" subtitle="Toutes les actions des utilisateurs : saisies, modifications, suppressions, connexions et opérations d'administration. Le contenu des formulaires n'est jamais conservé." />
    @include('admin._nav')

    {{-- Recherche : action (en clair ou en code), personne (nom ou e-mail), période, catégorie, adresse IP. --}}
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6" data-audit-search>
        <div class="lg:col-span-2"><label for="f_q" class="label">Action</label><input id="f_q" name="q" value="{{ request('q') }}" placeholder="séance, suppression, connexion, partage…" class="input"></div>
        <div class="lg:col-span-2"><label for="f_user" class="label">Utilisateur</label><input id="f_user" name="user" value="{{ request('user') }}" placeholder="Nom ou e-mail" class="input"></div>
        <div><label for="f_from" class="label">Du</label><input id="f_from" type="date" name="from" value="{{ request('from') }}" class="input"></div>
        <div><label for="f_to" class="label">Au</label><input id="f_to" type="date" name="to" value="{{ request('to') }}" class="input"></div>
        <div class="lg:col-span-2"><label for="f_cat" class="label">Catégorie</label>
            <select id="f_cat" name="category" class="input"><option value="">Toutes</option>@foreach ($categories as $key => $label)<option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="lg:col-span-2"><label for="f_ip" class="label">Adresse IP</label><input id="f_ip" name="ip" value="{{ request('ip') }}" placeholder="ex. 92.184." class="input"></div>
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
            <button class="btn-primary flex-1">Rechercher</button>
            @if (request()->hasAny(['q', 'user', 'from', 'to', 'category', 'ip']))<a href="{{ route('admin.audit.index') }}" class="btn-ghost">Effacer</a>@endif
            <a href="{{ route('admin.audit.export', request()->query()) }}" class="btn-secondary" data-audit-export><x-icon name="download" class="size-4" /> CSV</a>
        </div>
    </form>

    <p class="mb-2 text-sm text-slate-500" data-audit-total>{{ number_format($logs->total(), 0, ',', ' ') }} action{{ $logs->total() > 1 ? 's' : '' }}</p>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Action</th><th>Utilisateur</th><th>Concerne</th><th>Adresse IP</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($logs as $l)
            @php
                $horseId = $l->subject_type === (new \App\Models\Horse)->getMorphClass() ? $l->subject_id : ($l->properties['horse_id'] ?? null);
                $horse = $horseId ? $horses->get($horseId) : null;
                $details = collect($l->properties ?? [])->except(['auto', 'horse_id']);
            @endphp
            <tr data-audit-row>
                <td class="text-xs whitespace-nowrap">{{ $l->created_at->format('d/m/Y') }}<span class="block text-slate-500">{{ $l->created_at->format('H:i:s') }}</span></td>
                <td class="text-sm"><span class="font-medium text-slate-900">{{ \App\Support\AuditLabels::label($l->action) }}</span><span class="block font-mono text-[11px] text-slate-400">{{ $l->action }}</span></td>
                <td class="text-sm">
                    @if ($l->user)
                        <a href="{{ route('admin.audit.index', ['user' => $l->user->email]) }}" class="link">{{ $l->user->name }}</a><span class="block text-xs text-slate-500">{{ $l->user->email }}</span>
                    @else <span class="text-slate-500">Système</span> @endif
                </td>
                <td class="max-w-xs text-xs text-slate-600">
                    @if ($horse)<span class="font-medium text-slate-800">{{ $horse->shortName() }}</span>@endif
                    @if ($l->subject_type && ! ($horse && $l->subject_id === $horse->id && $l->subject_type === $horse->getMorphClass()))<span class="block">{{ class_basename($l->subject_type) }} #{{ $l->subject_id }}</span>@endif
                    @if ($l->organization)<span class="block text-slate-400">{{ $l->organization->name }}</span>@endif
                    @if ($details->isNotEmpty())<span class="block font-mono text-[10px] break-all text-slate-400">{{ \Illuminate\Support\Str::limit(json_encode($details, JSON_UNESCAPED_UNICODE), 160) }}</span>@endif
                </td>
                <td class="text-xs whitespace-nowrap text-slate-500"><a href="{{ route('admin.audit.index', ['ip' => $l->ip_address]) }}" class="hover:underline">{{ $l->ip_address }}</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-6 text-center text-sm text-slate-500">Aucune action ne correspond à cette recherche.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="mt-3">{{ $logs->links() }}</div>
    <p class="mt-4 text-xs text-slate-400">Conservation : 1 an pour les saisies courantes, 3 ans pour les opérations sensibles (partages, administration, facturation, exports).</p>
</x-layouts.app>
