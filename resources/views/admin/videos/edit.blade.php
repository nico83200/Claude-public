<x-layouts.app :title="$video->title.' · Vidéos'">
    <x-page-header :title="$video->title" :subtitle="$video->isPublished() ? 'Publiée le '.$video->published_at->translatedFormat('j F Y à H:i').' · '.$video->views_count.' vue(s)' : 'Non publiée'" :back="route('admin.videos.index')">
        <form method="POST" action="{{ route('admin.videos.publish', $video) }}">@csrf
            <button class="{{ $video->published_at ? 'btn-secondary' : 'btn-primary' }}" @disabled(! $video->video_path)>{{ $video->published_at ? 'Retirer' : 'Publier' }}</button>
        </form>
    </x-page-header>
    @include('admin._nav')
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-4">
            <x-section title="Aperçu">
                @if ($video->video_path)
                    <script type="application/json" id="admin-video">@json([\App\Http\Controllers\VideoController::payload($video)])</script>
                    <div x-data="videoPlayer({ videos: JSON.parse(document.getElementById('admin-video').textContent) })">
                        <button type="button" class="btn-secondary w-full" @click="playFrom(0)"><x-icon name="play" class="size-4" /> Lire avec la voix off et les sous-titres</button>
                        @include('videos._player')
                    </div>
                @else
                    <p class="text-sm text-slate-500">Ajoutez le fichier vidéo pour pouvoir la publier.</p>
                @endif
            </x-section>
            <x-section title="Informations">
                <form method="POST" action="{{ route('admin.videos.update', $video) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <x-field name="title" label="Titre" :value="$video->title" required maxlength="120" />
                    <x-textarea name="description" label="Description" :value="$video->description" rows="2" />
                    <x-select name="kind" label="Affichage" :options="\App\Models\Video::KINDS" :value="$video->kind" />
                    <x-select name="device" label="Appareils" :options="\App\Models\Video::DEVICES" :value="$video->device" />
                    <button class="btn-primary">Enregistrer</button>
                </form>
            </x-section>
        </div>
        <x-section title="Fichiers">
            <div class="space-y-5">
                @foreach (['video' => ['Vidéo', 'MP4 de préférence (lu partout, y compris sur iPhone). Sans son si une voix off est ajoutée ci-dessous.'], 'audio' => ['Voix off', 'Piste audio lue en même temps que la vidéo (MP3, M4A…). La vidéo est alors muette.'], 'captions' => ['Sous-titres', 'WebVTT (.vtt) ou SRT (.srt), affichés par défaut : utiles quand le son est coupé.'], 'poster' => ['Image d\'aperçu', 'Affichée avant la lecture (JPG, PNG, WebP).']] as $kind => [$label, $help])
                    @php [$column, $extensions, $maxMb] = \App\Models\Video::FILES[$kind]; $locked = $kind === 'video' && $video->isBuiltin(); @endphp
                    <div class="border-t border-slate-100 pt-4 first:border-0 first:pt-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium">{{ $label }}</p>
                            @if ($video->{$column})
                                <span class="flex items-center gap-2 text-xs">
                                    <x-badge color="green">Présent</x-badge>
                                    <a href="{{ $video->fileUrl($kind) }}" target="_blank" class="link">Ouvrir</a>
                                    @if ($kind !== 'video')<x-confirm-delete :action="route('admin.videos.remove-file', [$video, $kind])" message="Retirer ce fichier ?" class="text-red-600">Retirer</x-confirm-delete>@endif
                                </span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $help }} Maximum {{ $maxMb }} Mo.</p>
                        @if ($locked)
                            <p class="mt-2 text-xs text-slate-500">Vidéo livrée avec l'application : pour la remplacer, créez une nouvelle vidéo « Première connexion » et retirez celle-ci.</p>
                        @else
                            <div x-data="chunkUpload(@js(route('admin.videos.upload', [$video, $kind])), {{ $chunkBytes }}, @js($extensions))" class="mt-2">
                                <label class="btn-secondary cursor-pointer" :class="busy && 'pointer-events-none opacity-60'">
                                    <x-icon name="download" class="size-4 rotate-180" /> <span x-text="busy ? 'Envoi… ' + progress + ' %' : @js($video->{$column} ? 'Remplacer' : 'Choisir un fichier')"></span>
                                    <input type="file" class="sr-only" accept="{{ collect($extensions)->map(fn ($e) => '.'.$e)->implode(',') }}" @change="pick">
                                </label>
                                <p class="mt-2 text-sm text-red-700" x-show="error" x-text="error" x-cloak></p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-section>
    </div>
    <div class="mt-6">
        <x-confirm-delete :action="route('admin.videos.destroy', $video)" message="Supprimer cette vidéo ?" class="btn-ghost text-red-600">Supprimer la vidéo</x-confirm-delete>
    </div>
</x-layouts.app>
