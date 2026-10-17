@props(['deleteUrl' => null, 'text' => 'Élément supprimé', 'rounded' => 'rounded-3xl', 'surface' => '', 'validateUrl' => null, 'validateText' => 'Validé',
    'choices' => [], 'eventId' => null, 'date' => null, 'choiceTitle' => 'Que supprimer ?'])
{{-- Ligne d'une <x-swipe.list> : glisser à gauche pour supprimer, à droite pour valider (si une adresse est donnée).
     Sans aucune adresse (pas le droit), la ligne reste une ligne ordinaire. --}}
@if ($deleteUrl || $validateUrl || $choices)
    <div {{ $attributes->merge(['class' => 'relative']) }} data-swipe-row x-data="swipeRow({ deleteUrl: @js($deleteUrl), text: @js($text), validateUrl: @js($validateUrl), validateText: @js($validateText), choices: @js(array_values($choices)), eventId: @js($eventId), date: @js($date) })" x-show="! gone" x-transition:leave="transition duration-300" x-transition:leave-end="opacity-0 scale-y-75">
        {{-- Actions révélées par le glissé (invisibles au repos et à la souris) ; touchables seulement une fois la ligne ouverte,
             jamais pendant l'animation de démonstration. --}}
        @if ($deleteUrl || $choices)
            <div class="absolute inset-0 flex items-center justify-end overflow-hidden bg-red-600 transition-opacity {{ $rounded }}" :class="[x < 0 ? 'opacity-100' : 'opacity-0', open && x < 0 ? '' : 'pointer-events-none']" aria-hidden="true">
                <button type="button" data-swipe-action @click="askOrRemove()" :tabindex="open && x < 0 ? 0 : -1" class="flex h-full w-24 flex-col items-center justify-center gap-1 text-xs font-semibold text-white">
                    <x-icon name="trash" class="size-5" /> Supprimer
                </button>
            </div>
        @endif
        @if ($validateUrl)
            <div class="absolute inset-0 flex items-center justify-start overflow-hidden bg-emerald-600 transition-opacity {{ $rounded }}" :class="[x > 0 ? 'opacity-100' : 'opacity-0', open && x > 0 ? '' : 'pointer-events-none']" aria-hidden="true" data-swipe-validate>
                <button type="button" data-swipe-action @click="validate()" :tabindex="open && x > 0 ? 0 : -1" class="flex h-full w-24 flex-col items-center justify-center gap-1 text-xs font-semibold text-white">
                    <x-icon name="check" class="size-5" /> Valider
                </button>
            </div>
        @endif
        <div class="jc-swipe-layer relative {{ $surface }}" :style="style()">{{ $slot }}</div>
        @if ($choices)
            {{-- Série récurrente : cette occurrence, cette occurrence et les suivantes, ou toute la série. --}}
            <template x-teleport="body">
                <div x-show="choosing" x-cloak x-transition.opacity class="fixed inset-0 z-[80] flex items-end justify-center bg-slate-900/45" @click.self="choosing = false; close()" @keydown.escape.window="if (choosing) { choosing = false; close() }" role="dialog" aria-modal="true" :aria-label="@js($choiceTitle)" data-swipe-choices>
                    <div class="w-full max-w-md rounded-t-3xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-xl">
                        <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ $choiceTitle }}</h2>
                        <div class="space-y-2">
                            @foreach ($choices as $i => $choice)
                                <button type="button" @click="remove(choices[{{ $i }}])" class="flex min-h-14 w-full flex-col items-start justify-center rounded-2xl bg-slate-50 px-4 py-2 text-left hover:bg-red-50" data-swipe-choice="{{ $choice['scope'] }}">
                                    <span class="font-medium {{ $choice['scope'] === 'series' ? 'text-red-700' : 'text-slate-900' }}">{{ $choice['label'] }}</span>
                                    @if (! empty($choice['help']))<span class="text-xs text-slate-500">{{ $choice['help'] }}</span>@endif
                                </button>
                            @endforeach
                        </div>
                        <button type="button" class="btn-ghost mt-3 min-h-11 w-full justify-center" @click="choosing = false; close()">Annuler</button>
                    </div>
                </div>
            </template>
        @endif
    </div>
@else
    <div {{ $attributes->merge(['class' => 'relative']) }}>{{ $slot }}</div>
@endif
