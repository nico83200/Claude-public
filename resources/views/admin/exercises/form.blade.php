<x-layouts.app :title="($exercise->exists ? $exercise->name : 'Nouvel exercice').' · Admin'">
    <x-page-header :title="$exercise->exists ? $exercise->name : 'Nouvel exercice'" :subtitle="$exercise->exists ? 'Bibliothèque par défaut · version '.$exercise->version.($exercise->archived_at ? ' · retiré' : '') : 'Bibliothèque par défaut : visible par tous les utilisateurs'" :back="route('admin.exercises.index')">
        @if ($exercise->exists)
            <a href="{{ route('exercises.show', $exercise) }}" class="btn-secondary">Voir comme un utilisateur</a>
            <form method="POST" action="{{ route('admin.exercises.archive', $exercise) }}">@csrf<button class="btn-ghost">{{ $exercise->archived_at ? 'Remettre dans la bibliothèque' : 'Retirer de la bibliothèque' }}</button></form>
        @endif
    </x-page-header>
    @include('admin._nav')

    <div class="grid gap-4 lg:grid-cols-3">
        <form method="POST" action="{{ $exercise->exists ? route('admin.exercises.update', $exercise) : route('admin.exercises.store') }}" class="space-y-4 lg:col-span-2">
            @csrf @if ($exercise->exists) @method('PUT') @endif
            <x-section>
                @include('exercises._fields')
                <p class="mt-3 text-xs text-slate-500">Les séances déjà enregistrées conservent la version de l'exercice utilisée.</p>
            </x-section>
            <button class="btn-primary">Enregistrer</button>
        </form>

        <x-section title="Démonstration" data-exercise-media>
            @if (! $exercise->exists)
                <p class="text-sm text-slate-500">Enregistrez d'abord l'exercice, puis ajoutez une image ou une vidéo qui montre l'exemple.</p>
            @else
                @if ($exercise->media_type === 'video')
                    <video src="{{ $exercise->mediaSrc() }}" controls playsinline preload="metadata" class="w-full rounded-2xl bg-black"></video>
                @elseif ($exercise->media_type === 'image')
                    <a href="{{ $exercise->mediaSrc() }}" data-lightbox="exercise-{{ $exercise->id }}" data-caption="{{ $exercise->name }}"><img src="{{ $exercise->mediaSrc() }}" alt="{{ $exercise->name }}" class="w-full rounded-2xl object-cover"></a>
                @else
                    <p class="rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Pas encore de démonstration.</p>
                @endif
                <div x-data="chunkUpload(@js(route('admin.exercises.upload', $exercise)), {{ $chunkBytes }}, @js(array_keys(\App\Models\Exercise::MEDIA_MIMES)))" class="mt-3">
                    <label class="btn-secondary cursor-pointer" :class="busy && 'pointer-events-none opacity-60'">
                        <x-icon name="download" class="size-4 rotate-180" /> <span x-text="busy ? 'Envoi… ' + progress + ' %' : @js($exercise->media_path ? 'Remplacer' : 'Ajouter une image ou une vidéo')"></span>
                        <input type="file" class="sr-only" accept="{{ collect(array_keys(\App\Models\Exercise::MEDIA_MIMES))->map(fn ($e) => '.'.$e)->implode(',') }}" @change="pick">
                    </label>
                    <p class="mt-2 text-sm text-red-700" x-show="error" x-text="error" x-cloak></p>
                </div>
                <p class="mt-2 text-xs text-slate-500">Image JPEG, PNG ou WebP (15 Mo maximum) ou vidéo MP4, WebM ou MOV (200 Mo maximum). Format vertical conseillé pour le téléphone ; une vidéo courte (15 à 60 s) et sans son se regarde plus facilement à l'écurie.</p>
                @if ($exercise->media_path)
                    <x-confirm-delete :action="route('admin.exercises.remove-media', $exercise)" message="Retirer la démonstration de cet exercice ?" class="mt-3 text-sm text-red-600">Retirer la démonstration</x-confirm-delete>
                @endif
            @endif
        </x-section>
    </div>
</x-layouts.app>
