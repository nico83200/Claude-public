<x-layouts.app title="Modèles de séances">
    <x-page-header title="Modèles de séances" subtitle="Créés depuis une séance (« Enregistrer comme modèle »)" :back="route('sessions.index')" />
    @if ($templates->isEmpty())
        <x-empty title="Aucun modèle" icon="book">Ouvrez une séance bien construite et enregistrez-la comme modèle pour la réutiliser.</x-empty>
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($templates as $t)
                <div class="card p-4">
                    <div class="flex items-start justify-between"><div><p class="font-semibold">{{ $t->name }}</p><p class="text-sm text-slate-500">{{ \App\Models\RidingSession::TYPES[$t->session_type] ?? '' }} · {{ count($t->items ?? []) }} exercice(s) {{ $t->organization_id ? '· partagé' : '' }}</p></div>
                        @if ($t->user_id === auth()->id())<x-confirm-delete :action="route('templates.destroy', $t)" message="Supprimer ce modèle ?" />@endif</div>
                    <ul class="mt-2 list-disc pl-5 text-sm text-slate-600">@foreach (collect($t->items)->take(6) as $i)<li>{{ $i['name'] ?? 'Exercice' }}</li>@endforeach</ul>
                    <a href="{{ route('sessions.create') }}" class="mt-3 inline-block text-sm link">Créer une séance à partir de ce modèle</a>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
