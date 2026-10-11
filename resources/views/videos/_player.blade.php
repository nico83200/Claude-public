{{-- Lecteur plein écran (voix off synchronisée, sous-titres). Attend un composant Alpine videoPlayer parent. --}}
<div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-[90] flex flex-col bg-slate-950/95 text-white" role="dialog" aria-modal="true" :aria-label="current?.title">
    <template x-if="current">
        <div class="mx-auto flex h-full w-full max-w-3xl flex-col px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-[max(1rem,env(safe-area-inset-bottom))]">
            <div class="flex items-center gap-3 py-2">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium tracking-wide text-white/50 uppercase" x-text="current.kind === 'onboarding' ? 'Bienvenue' : 'Nouveauté'"></p>
                    <h2 class="truncate text-lg font-semibold !text-white" x-text="current.title"></h2>
                </div>
                <span x-show="videos.length > 1 && {{ ($auto ?? false) ? 'true' : 'false' }}" class="text-sm text-white/60" x-text="(index + 1) + ' / ' + videos.length"></span>
                <button type="button" @click="skip()" class="rounded-full bg-white/10 px-4 py-2 text-sm font-medium hover:bg-white/20">{{ ($auto ?? false) ? 'Passer' : 'Fermer' }}</button>
            </div>
            <div class="relative flex min-h-0 flex-1 items-center justify-center">
                <video x-ref="video" :key="current.id" class="max-h-full w-auto max-w-full rounded-2xl bg-black" :poster="current.poster" controls playsinline preload="metadata"
                       x-init="$nextTick(() => bind())" @ended="ended()">
                    <template x-if="current.captions"><track kind="subtitles" srclang="fr" label="Français" :src="current.captions" default></template>
                </video>
                <template x-if="current.audio"><audio x-ref="audio" :src="current.audio" preload="auto"></audio></template>
            </div>
            <p x-show="current.description" x-text="current.description" class="mt-3 text-center text-sm text-white/70"></p>
            <button type="button" @click="ended()" class="btn mt-3 self-center bg-white px-8 text-slate-900 hover:bg-white/90"
                    x-text="{{ ($auto ?? false) ? 'true' : 'false' }} && index < videos.length - 1 ? 'Vidéo suivante' : 'C\'est parti !'"></button>
        </div>
    </template>
</div>
