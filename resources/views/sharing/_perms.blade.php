{{-- $selected: permissions cochées ; $prefix: nom du champ.
     Profil en premier (gros boutons) ; les cases détaillées restent dans « Personnaliser les droits ». --}}
@php
    $profiles = [
        'half_lease' => ['Demi-pensionnaire', 'Voit les soins, note ses séances et le suivi du jour.', $presets['half_lease']],
        'boarding' => ['Écurie / pension', 'Gère soins, alimentation et suivi au quotidien.', $presets['boarding']],
        'read' => ['Lecture seule', 'Consulte la fiche du cheval, sans rien modifier.', ['horse.view']],
    ];
    $sel = array_values($selected);
    $current = collect($profiles)->search(fn ($p) => collect($p[2])->sort()->values()->all() === collect($sel)->sort()->values()->all()) ?: 'custom';
@endphp
<div x-data="{ sel: @js($sel), profile: @js($current), custom: @js($current === 'custom'), profiles: @js(collect($profiles)->map(fn ($p) => $p[2])) }" class="space-y-2" data-perm-profiles>
    <p class="label">Droits</p>
    <div class="grid gap-2 sm:grid-cols-3" role="radiogroup" aria-label="Profil">
        @foreach ($profiles as $key => [$title, $hint])
            <button type="button" role="radio" :aria-checked="profile === @js($key)" @click="profile = @js($key); sel = [...profiles[@js($key)]]"
                    class="min-h-14 rounded-2xl px-3 py-2 text-left ring-2 transition" :class="profile === @js($key) ? 'bg-brand-50 ring-brand-600' : 'bg-white ring-slate-200'">
                <span class="block text-sm font-semibold text-slate-900">{{ $title }}</span><span class="block text-xs text-slate-500">{{ $hint }}</span>
            </button>
        @endforeach
    </div>
    <button type="button" class="btn-ghost min-h-10 px-2 text-sm" @click="custom = ! custom" :aria-expanded="custom" x-text="custom ? 'Masquer le détail des droits' : 'Personnaliser les droits'">Personnaliser les droits</button>
    <div x-show="custom" x-cloak class="grid gap-x-4 rounded-2xl bg-slate-50 p-3 sm:grid-cols-2">
        @foreach ($labels as $key => $label)
            <label class="flex min-h-10 items-center gap-2 text-sm"><input type="checkbox" name="{{ $prefix }}[]" value="{{ $key }}" x-model="sel" @change="profile = 'custom'" class="size-5 rounded" @if ($key === 'horse.view') onclick="return false" checked @endif> {{ $label }}@if ($key === 'horse.view')<span class="text-xs text-slate-400">(toujours inclus)</span>@endif</label>
        @endforeach
    </div>
    {{-- Repliées, les cases restent envoyées avec le formulaire. --}}
</div>
