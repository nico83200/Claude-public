<x-layouts.app title="Tutoriels">
    <x-page-header title="Tutoriels et nouveautés" subtitle="Revoyez à tout moment la présentation de Jack&Cie et les nouveautés." />
    @php $payload = $videos->map(fn ($v) => \App\Http\Controllers\VideoController::payload($v))->values(); @endphp
    @if ($videos->isEmpty())
        <x-empty title="Aucune vidéo pour le moment" icon="play" />
    @else
        <script type="application/json" id="library-videos">@json($payload)</script>
        <div x-data="videoPlayer({ videos: JSON.parse(document.getElementById('library-videos').textContent) })" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($videos as $i => $video)
                <button type="button" @click="playFrom({{ $i }})" class="card group overflow-hidden text-left transition hover:shadow-md">
                    <div class="relative aspect-video bg-brand-900">
                        @if ($video->fileUrl('poster'))<img src="{{ $video->fileUrl('poster') }}" alt="" class="size-full object-cover opacity-90" loading="lazy">@endif
                        <span class="absolute inset-0 flex items-center justify-center"><span class="flex size-14 items-center justify-center rounded-full bg-white/90 text-brand-900 shadow-lg transition group-hover:scale-105"><x-icon name="play" class="size-6" /></span></span>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center gap-2">
                            <x-badge :color="$video->kind === 'onboarding' ? 'brand' : 'blue'">{{ $video->kind === 'onboarding' ? 'Tutoriel' : 'Nouveauté' }}</x-badge>
                            @if ($video->device !== 'all')<x-badge>{{ $video->device === 'mobile' ? 'Téléphone' : 'Ordinateur' }}</x-badge>@endif
                            @if ($seen->has($video->id))<span class="text-xs text-slate-400">Déjà vue</span>@endif
                        </div>
                        <p class="mt-2 font-semibold text-slate-900">{{ $video->title }}</p>
                        @if ($video->description)<p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $video->description }}</p>@endif
                        <p class="mt-2 text-xs text-slate-400">{{ $video->published_at->translatedFormat('j F Y') }}</p>
                    </div>
                </button>
            @endforeach
            @include('videos._player')
        </div>
    @endif
</x-layouts.app>
