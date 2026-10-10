<x-layouts.app title="RGPD · Admin">
    <x-page-header title="Demandes RGPD" subtitle="À traiter sous un mois" />
    @include('admin._nav')
    @forelse ($requests as $r)
        <div class="card mb-2 p-4 text-sm">
            <div class="flex flex-wrap justify-between gap-2"><span><strong>{{ $r->type === 'deletion' ? 'Suppression' : ($r->type === 'export' ? 'Export' : 'Rectification') }}</strong> — {{ $r->email }} · {{ $r->created_at->format('d/m/Y') }}</span><x-badge :color="['done' => 'green', 'rejected' => 'red', 'open' => 'amber'][$r->status] ?? 'slate'">{{ $r->status }}</x-badge></div>
            @if ($r->details)<p class="mt-1 whitespace-pre-line text-slate-600">{{ $r->details }}</p>@endif
            @if ($r->handled_at)<p class="text-xs text-slate-500">Traité le {{ $r->handled_at->format('d/m/Y') }} par {{ $r->handler?->name }}</p>@endif
            <form method="POST" action="{{ route('admin.data-requests.update', $r) }}" class="mt-2 flex flex-wrap items-end gap-2" @if ($r->type === 'deletion') onsubmit="return ! this.execute_deletion?.checked || confirm('Anonymiser définitivement ce compte ?')" @endif>
                @csrf @method('PUT')
                <x-select name="status" :options="['open' => 'Reçue', 'in_progress' => 'En cours', 'done' => 'Traitée', 'rejected' => 'Refusée']" :value="$r->status" aria-label="Statut" />
                @if ($r->type === 'deletion' && $r->status !== 'done' && $r->user)<label class="flex items-center gap-2 text-xs"><input type="checkbox" name="execute_deletion" value="1" class="rounded"> Exécuter l'anonymisation</label>@endif
                <button class="btn-secondary">Mettre à jour</button>
            </form>
        </div>
    @empty <x-empty title="Aucune demande" icon="shield" /> @endforelse
    {{ $requests->links() }}
</x-layouts.app>
