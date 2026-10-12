<x-layouts.app title="Vidéos · Admin">
    <x-page-header title="Vidéos" subtitle="Tutoriel de première connexion et nouveautés affichées au prochain chargement de la page d'accueil" />
    @include('admin._nav')
    <div class="grid gap-4 lg:grid-cols-5">
        <div class="space-y-3 lg:col-span-3">
            @forelse ($videos as $video)
                <a href="{{ route('admin.videos.edit', $video) }}" class="card flex items-center gap-4 p-4 transition hover:shadow-md">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl {{ $video->isPublished() ? 'bg-brand-50 text-brand-800' : 'bg-slate-100 text-slate-400' }}"><x-icon name="play" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-slate-900">{{ $video->title }}</span>
                        <span class="block text-xs text-slate-500">{{ $video->kind === 'onboarding' ? 'Première connexion' : 'Nouveauté' }} · {{ \App\Models\Video::DEVICES[$video->device] ?? '' }} · {{ $video->views_count }} vue(s), {{ $video->completed_count }} jusqu'au bout{{ $video->audio_path ? ' · voix off' : '' }}{{ $video->captions_path ? ' · sous-titres' : '' }}</span>
                    </span>
                    <x-badge :color="$video->isPublished() ? 'green' : 'slate'">{{ $video->isPublished() ? 'Publiée' : ($video->video_path ? 'Brouillon' : 'Sans fichier') }}</x-badge>
                </a>
            @empty
                <x-empty title="Aucune vidéo" icon="play" />
            @endforelse
        </div>
        <x-section title="Nouvelle vidéo" class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.videos.store') }}" class="space-y-3">
                @csrf
                <x-field name="title" label="Titre" required maxlength="120" placeholder="ex. Nouveau : les dépenses récurrentes" />
                <x-textarea name="description" label="Description (facultatif)" rows="2" />
                <x-select name="kind" label="Affichage" :options="\App\Models\Video::KINDS" value="announcement" />
                <x-select name="device" label="Appareils" :options="\App\Models\Video::DEVICES" value="all" />
                <button class="btn-primary w-full">Créer, puis ajouter le fichier</button>
            </form>
            <p class="mt-3 text-xs text-slate-500">Une nouveauté s'affiche à chaque utilisateur au prochain chargement de sa page d'accueil, jusqu'à ce qu'il l'ait vue ou passée. Les vidéos restent consultables dans le menu « Tutoriels ».</p>
        </x-section>
    </div>
</x-layouts.app>
