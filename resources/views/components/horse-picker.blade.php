@props(['horses', 'route', 'label', 'icon' => 'plus', 'title' => 'Quel cheval ?', 'hint' => null, 'class' => 'btn-secondary', 'fragment' => '', 'key'])
{{-- Bouton d'action pour un cheval : lien direct s'il n'y en a qu'un, sinon fenêtre de choix du cheval. --}}
@if ($horses->count() === 1)
    <a href="{{ route($route, $horses->first()).$fragment }}" class="{{ $class }}" data-horse-picker="{{ $key }}"><x-icon :name="$icon" class="size-4" /> {{ $label }}</a>
@elseif ($horses->count() > 1)
    <div x-data="{ open: false }" @keydown.escape.window="open = false">
        <button type="button" class="{{ $class }}" @click="open = true" data-horse-picker="{{ $key }}"><x-icon :name="$icon" class="size-4" /> {{ $label }}</button>
        <template x-teleport="body">
            <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[70] flex items-end justify-center bg-slate-900/45 sm:items-center" @click.self="open = false" role="dialog" aria-modal="true" data-horse-picker-sheet="{{ $key }}">
                <div class="w-full max-w-md rounded-t-3xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-xl sm:rounded-3xl">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">{{ $title }}</h2>
                        <button type="button" class="btn-ghost px-2" @click="open = false" aria-label="Fermer"><x-icon name="x" /></button>
                    </div>
                    @if ($hint)<p class="mb-3 text-sm text-slate-500">{{ $hint }}</p>@endif
                    <div class="max-h-[60dvh] space-y-1 overflow-y-auto">
                        @foreach ($horses as $h)
                            <a href="{{ route($route, $h).$fragment }}" class="flex min-h-14 items-center gap-3 rounded-2xl px-3 py-2 hover:bg-slate-50">
                                @if ($h->mainPhotoUrl())<img src="{{ $h->mainPhotoUrl() }}" alt="" loading="lazy" class="size-10 rounded-xl object-cover">@else<span class="flex size-10 items-center justify-center rounded-xl bg-brand-100 font-bold text-brand-800">{{ mb_substr($h->shortName(), 0, 1) }}</span>@endif
                                <span class="flex-1 font-medium">{{ $h->shortName() }}</span>
                                <x-icon name="chevron" class="size-5 text-slate-300" />
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </template>
    </div>
@endif
