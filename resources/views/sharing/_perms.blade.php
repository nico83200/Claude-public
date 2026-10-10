{{-- $selected: permissions cochées ; $prefix: nom du champ --}}
<div x-data="{ sel: @js(array_values($selected)) }" class="space-y-2">
    <div class="flex flex-wrap gap-2 text-xs">
        <button type="button" class="btn-ghost px-2 text-xs" @click="sel = @js($presets['half_lease'])">Préréglage demi-pension</button>
        <button type="button" class="btn-ghost px-2 text-xs" @click="sel = @js($presets['boarding'])">Préréglage pension / écurie</button>
        <button type="button" class="btn-ghost px-2 text-xs" @click="sel = ['horse.view']">Consultation seule</button>
    </div>
    <div class="grid gap-x-4 sm:grid-cols-2">
        @foreach ($labels as $key => $label)
            <label class="flex min-h-9 items-center gap-2 text-sm"><input type="checkbox" name="{{ $prefix }}[]" value="{{ $key }}" x-model="sel" class="rounded" @if ($key === 'horse.view') onclick="return false" checked @endif> {{ $label }}</label>
        @endforeach
    </div>
</div>
