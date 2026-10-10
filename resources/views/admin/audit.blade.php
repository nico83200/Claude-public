<x-layouts.app title="Journal · Admin">
    <x-page-header title="Journal d'audit" />
    @include('admin._nav')
    <form class="mb-4 flex flex-wrap gap-2"><input name="action" value="{{ request('action') }}" placeholder="Action (ex. admin., grant.)" class="input max-w-xs" aria-label="Action"><input name="user" value="{{ request('user') }}" placeholder="Email utilisateur" class="input max-w-xs" aria-label="Utilisateur"><button class="btn-secondary">Filtrer</button></form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Action</th><th>Utilisateur</th><th>Organisation</th><th>Détails</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @foreach ($logs as $l)
            <tr><td class="text-xs whitespace-nowrap">{{ $l->created_at->format('d/m/Y H:i:s') }}</td><td class="font-mono text-xs">{{ $l->action }}</td><td class="text-xs">{{ $l->user?->email ?? 'système' }}<span class="block text-slate-400">{{ $l->ip_address }}</span></td><td class="text-xs">{{ $l->organization?->name }}</td><td class="max-w-md font-mono text-[10px] break-all text-slate-500">{{ $l->subject_type ? class_basename($l->subject_type).'#'.$l->subject_id : '' }} {{ $l->properties ? \Illuminate\Support\Str::limit(json_encode($l->properties, JSON_UNESCAPED_UNICODE), 200) : '' }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="mt-3">{{ $logs->links() }}</div>
</x-layouts.app>
