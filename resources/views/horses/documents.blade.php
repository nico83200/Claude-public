<x-layouts.app :title="'Documents · '.$horse->shortName()">
    <x-page-header :title="$horse->shortName()" subtitle="Documents" :back="route('horses.show', $horse)" />
    @include('horses._tabs')
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if ($documents->isEmpty())
                <x-empty title="Aucun document" icon="doc">Comptes rendus, ordonnances, factures, résultats d'examens…</x-empty>
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Document</th><th>Catégorie</th><th>Ajouté</th><th></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($documents as $d)
                        <tr>
                            <td><a href="{{ route('horses.documents.download', [$horse, $d]) }}" class="link">{{ $d->title }}</a><span class="block text-xs text-slate-500">{{ $d->original_name }} · {{ number_format($d->size_bytes / 1024, 0, ',', ' ') }} Ko</span></td>
                            <td>{{ \App\Models\HorseDocument::CATEGORIES[$d->category] }} @if ($d->is_sensitive)<x-badge color="purple">Santé</x-badge>@endif</td>
                            <td class="text-xs text-slate-500">{{ $d->created_at->format('d/m/Y') }}<br>{{ $d->uploader?->name }}</td>
                            <td>@if ($canUpload && $d->uploaded_by === auth()->id())<x-confirm-delete :action="route('horses.documents.destroy', [$horse, $d])" message="Supprimer ce document ?" />@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
        @if ($canUpload)
        <x-section title="Ajouter un document">
            <form method="POST" action="{{ route('horses.documents.store', $horse) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div><label class="label" for="doc-file">Fichier</label><input id="doc-file" type="file" name="file" required class="text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.txt,.doc,.docx,.xls,.xlsx,.csv">@error('file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <x-field name="title" label="Titre" />
                <x-select name="category" label="Catégorie" :options="\App\Models\HorseDocument::CATEGORIES" value="report" />
                <x-checkbox name="is_sensitive" label="Document de santé (réservé aux personnes autorisées à voir les soins)" :checked="true" />
                <button class="btn-primary w-full">Envoyer</button>
                <p class="text-xs text-slate-500">Stockage privé : accessible uniquement après vérification de vos droits.</p>
            </form>
        </x-section>
        @endif
    </div>
</x-layouts.app>
