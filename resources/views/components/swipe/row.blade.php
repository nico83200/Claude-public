@props(['deleteUrl' => null, 'text' => 'Élément supprimé', 'rounded' => 'rounded-3xl', 'surface' => '', 'validateUrl' => null, 'validateText' => 'Validé'])
{{-- Ligne d'une <x-swipe.list> : glisser à gauche pour supprimer, à droite pour valider (si une adresse est donnée).
     Sans aucune adresse (pas le droit), la ligne reste une ligne ordinaire. --}}
@if ($deleteUrl || $validateUrl)
    <div {{ $attributes->merge(['class' => 'relative']) }} data-swipe-row x-data="swipeRow({ deleteUrl: @js($deleteUrl), text: @js($text), validateUrl: @js($validateUrl), validateText: @js($validateText) })" x-show="! gone" x-transition:leave="transition duration-300" x-transition:leave-end="opacity-0 scale-y-75">
        {{-- Actions révélées par le glissé (invisibles au repos et à la souris) ; touchables seulement une fois la ligne ouverte,
             jamais pendant l'animation de démonstration. --}}
        @if ($deleteUrl)
            <div class="absolute inset-0 flex items-center justify-end overflow-hidden bg-red-600 transition-opacity {{ $rounded }}" :class="[x < 0 ? 'opacity-100' : 'opacity-0', open && x < 0 ? '' : 'pointer-events-none']" aria-hidden="true">
                <button type="button" data-swipe-action @click="remove()" :tabindex="open && x < 0 ? 0 : -1" class="flex h-full w-24 flex-col items-center justify-center gap-1 text-xs font-semibold text-white">
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
    </div>
@else
    <div {{ $attributes->merge(['class' => 'relative']) }}>{{ $slot }}</div>
@endif
