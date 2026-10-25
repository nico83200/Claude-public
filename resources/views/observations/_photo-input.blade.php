{{-- Photos (appareil photo ou galerie), jusqu'à 6. --}}
<div x-data="{ n: 0 }">
    <label for="{{ $id }}" class="btn-secondary min-h-11 cursor-pointer justify-center"><x-icon name="camera" class="size-4" /> Joindre des photos</label>
    <input id="{{ $id }}" name="photos[]" type="file" accept="image/*" multiple class="sr-only" @change="n = $event.target.files.length">
    <span class="ml-2 text-sm text-slate-500" x-show="n" x-text="n + (n > 1 ? ' photos prêtes' : ' photo prête')" x-cloak></span>
    @error('photos')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    @error('photos.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
