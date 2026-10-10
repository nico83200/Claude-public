<x-layouts.app title="Mes données">
    <x-page-header title="Paramètres" />
    @include('settings._nav')
    <div class="grid max-w-4xl gap-4 lg:grid-cols-2">
        <x-section title="Exporter mes données">
            <p class="mb-3 text-sm text-slate-600">Téléchargez vos données personnelles et vos contributions au format JSON (portabilité). Les dossiers de chevaux peuvent aussi être exportés en PDF depuis chaque fiche.</p>
            <a href="{{ route('settings.export') }}" class="btn-primary"><x-icon name="download" class="size-4" /> Télécharger</a>
        </x-section>
        <x-section title="Rectification ou suppression">
            <form method="POST" action="{{ route('settings.data-request') }}" class="space-y-3">
                @csrf
                <x-select name="type" label="Demande" :options="['rectification' => 'Rectification de données', 'deletion' => 'Suppression de mon compte']" />
                <x-textarea name="details" label="Précisions" />
                <button class="btn-secondary w-full">Envoyer la demande</button>
                <p class="text-xs text-slate-500">Une suppression ferme vos accès, anonymise votre compte et supprime les chevaux de votre espace personnel (purge définitive après 30 jours). Pensez à exporter vos données et à transférer les chevaux partagés avant.</p>
            </form>
            @foreach ($requests as $r)<p class="mt-2 text-xs text-slate-500">{{ $r->created_at->format('d/m/Y') }} — {{ $r->type === 'deletion' ? 'Suppression' : 'Rectification' }} : {{ ['open' => 'reçue', 'in_progress' => 'en cours', 'done' => 'traitée', 'rejected' => 'refusée'][$r->status] }}</p>@endforeach
        </x-section>
    </div>
</x-layouts.app>
