@props(['hintKey', 'hint' => null, 'hintClass' => ''])
{{-- Liste dont les lignes (<x-swipe.row>) se glissent vers la gauche pour être supprimées, avec « Annuler » (resources/js/swipe-delete.js).
     L'astuce s'affiche sur téléphone et tablette jusqu'à ce que le geste soit utilisé ou l'astuce fermée. --}}
<div x-data="swipeList({ hintKey: @js($hintKey) })" @swipe-deleted="deleted($event.detail)" @swipe-failed="failed($event.detail)" {{ $attributes }}>
    @if ($hint)
        <div x-show="hint" x-cloak class="mb-3 flex items-center gap-3 rounded-2xl bg-brand-50 px-4 py-3 text-sm text-brand-900 {{ $hintClass }}" data-swipe-hint>
            <x-icon name="arrow-left" class="size-5 shrink-0 text-brand-700" />
            <p class="flex-1"><strong>Astuce :</strong> {{ $hint }}</p>
            <button type="button" class="btn-ghost min-h-9 shrink-0 px-2 text-sm" @click="dismissHint()">Compris</button>
        </div>
    @endif
    {{ $slot }}
    {{-- Message après suppression, avec « Annuler » (au-dessus de la barre du bas). --}}
    <div x-show="toast" x-cloak x-transition.opacity class="fixed inset-x-4 bottom-[calc(env(safe-area-inset-bottom)+6.5rem)] z-50 mx-auto flex max-w-md items-center gap-3 rounded-2xl px-4 py-3 text-sm text-white shadow-lg lg:bottom-8" :class="toast?.error ? 'bg-red-700' : 'bg-slate-900'" role="status" aria-live="polite" data-swipe-toast>
        <span class="flex-1" x-text="toast?.text"></span>
        <button type="button" x-show="toast?.restoreUrl" @click="undo()" class="font-semibold text-gold-200 underline-offset-4 hover:underline">Annuler</button>
    </div>
    {{-- Élément lié (ex. soin rattaché à la dépense supprimée) : suppression proposée, au-dessus du message. --}}
    <div x-show="toast?.linked" x-cloak x-transition.opacity class="fixed inset-x-4 bottom-[calc(env(safe-area-inset-bottom)+10.5rem)] z-50 mx-auto flex max-w-md flex-wrap items-center gap-3 rounded-2xl bg-white px-4 py-3 text-sm text-slate-900 shadow-lg ring-1 ring-slate-200 lg:bottom-24" data-swipe-linked>
        <span class="flex-1" x-text="toast?.linked?.text"></span>
        <button type="button" @click="deleteLinked()" class="btn-danger min-h-9 px-3 text-sm">Supprimer</button>
        <button type="button" @click="toast.linked = null" class="btn-ghost min-h-9 px-2 text-sm">Garder</button>
    </div>
</div>
