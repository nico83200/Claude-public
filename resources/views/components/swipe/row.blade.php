@props(['deleteUrl' => null, 'text' => 'Élément supprimé', 'rounded' => 'rounded-3xl', 'surface' => ''])
{{-- Ligne d'une <x-swipe.list>. Sans adresse de suppression (pas le droit), la ligne reste une ligne ordinaire. --}}
@if ($deleteUrl)
    <div {{ $attributes->merge(['class' => 'relative']) }} data-swipe-row x-data="swipeRow({ deleteUrl: @js($deleteUrl), text: @js($text) })" x-show="! gone" x-transition:leave="transition duration-300" x-transition:leave-end="opacity-0 scale-y-75">
        {{-- Action révélée par le glissé (invisible au repos et à la souris) ; touchable seulement une fois la ligne ouverte,
             jamais pendant l'animation de démonstration. --}}
        <div class="absolute inset-0 flex items-center justify-end overflow-hidden bg-red-600 transition-opacity {{ $rounded }}" :class="[x < 0 ? 'opacity-100' : 'opacity-0', open ? '' : 'pointer-events-none']" aria-hidden="true">
            <button type="button" data-swipe-action @click="remove()" :tabindex="open ? 0 : -1" class="flex h-full w-24 flex-col items-center justify-center gap-1 text-xs font-semibold text-white">
                <x-icon name="trash" class="size-5" /> Supprimer
            </button>
        </div>
        <div class="jc-swipe-layer relative {{ $surface }}" :style="style()">{{ $slot }}</div>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'relative']) }}>{{ $slot }}</div>
@endif
