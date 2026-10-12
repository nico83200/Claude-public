{{-- « Pour vous » : exercices habituels de l'utilisateur et exercices adaptés à son galop, par étape de séance
     (mêmes critères que la préparation d'une séance). Galop inconnu : question d'abord, puis résultats sans recharger. --}}
@php $phaseLabels = \App\Models\RidingSession::PHASES; @endphp
<section class="card mb-4 p-4 sm:p-5" data-for-you
    x-data="{ galop: @js($suggestions['galop']), askGalop: @js($suggestionVariants !== []), phase: 'warmup', error: '',
        key() { return @js($suggestionVariants !== []) && this.galop ? (this.galop <= 3 ? '2' : (this.galop <= 5 ? '4' : '6')) : ''; },
        async saveGalop(g) {
            this.error = '';
            try {
                const res = await fetch(@js(route('settings.riding-level')), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ galop: g }) });
                if (! res.ok) throw new Error();
            } catch { this.error = 'Niveau non enregistré dans votre fiche (connexion ?) ; il est utilisé sur cette page.'; }
            this.galop = g; this.askGalop = false;
        } }">
    <h2 class="flex items-center gap-2 text-[17px] font-semibold"><x-icon name="sparkles" class="size-5 text-brand-700" /> Pour vous<span class="text-sm font-medium text-brand-700" x-show="galop" x-text="'· Galop ' + galop"></span></h2>

    @if ($suggestionVariants !== [])
        <div x-show="askGalop" class="mt-3" data-ask-galop>
            <p class="font-medium text-slate-900">Quel est votre niveau ?</p>
            <p class="text-sm text-slate-600">Pour vous proposer des exercices adaptés. Il sera enregistré dans votre fiche.</p>
            <div class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-7">
                @foreach (\App\Models\UserProfile::GALOPS as $g => $gl)
                    <button type="button" class="btn-secondary min-h-11 px-2 text-sm" @click="saveGalop({{ $g }})" data-galop="{{ $g }}">{{ $gl }}</button>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-500">Galops 1 à 3 : débutant · 4 et 5 : intermédiaire · 6 et plus : expert. <button type="button" class="font-medium underline" @click="askGalop = false">Passer</button></p>
        </div>
    @endif

    <div x-show="! askGalop" @if ($suggestionVariants !== []) x-cloak @endif>
        <p x-show="error" x-cloak x-text="error" class="mt-2 rounded-xl bg-amber-50 p-2 text-xs text-amber-900"></p>
        <div class="-mx-1 mt-3 flex gap-1 overflow-x-auto pb-1" role="tablist">
            @foreach ($phaseLabels as $p => $pl)
                <button type="button" role="tab" @click="phase = @js($p)" :aria-selected="phase === @js($p)" class="shrink-0 rounded-full px-3 py-1.5 text-sm font-medium" :class="phase === @js($p) ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-700'">{{ $pl }}</button>
            @endforeach
        </div>
        @foreach (['' => $suggestions] + $suggestionVariants as $vk => $variant)
            @foreach ($variant['phases'] as $p => $items)
                <div x-show="phase === @js($p) && key() === @js((string) $vk)" @if ($vk !== '' || $p !== 'warmup') x-cloak @endif class="mt-3" data-for-you-phase="{{ $p }}" data-variant="{{ $vk }}">
                    @forelse ($items as $sug)
                        @php $ex = $suggestionLibrary[$sug['id']]; @endphp
                        <a href="{{ route('exercises.show', $ex) }}" class="flex items-start justify-between gap-3 border-t border-slate-100 py-2.5 text-sm first:border-0 hover:bg-slate-50">
                            <span class="min-w-0"><span class="font-medium text-slate-900">{{ $ex->name }}</span><span class="block text-xs text-brand-800">{{ $sug['reason'] }}</span><span class="block text-xs text-slate-500">{{ $ex->category?->name }} · {{ \App\Models\Exercise::LEVELS[$ex->level] }}{{ $ex->duration_minutes ? ' · '.$ex->duration_minutes.' min' : '' }}</span></span>
                            <x-icon name="chevron" class="mt-1 size-4 shrink-0 text-slate-300" />
                        </a>
                    @empty
                        <p class="py-2 text-sm text-slate-500">{{ $vk === '' && ! $suggestions['galop'] ? 'Indiquez votre galop pour recevoir des suggestions, ou faites quelques séances : vos exercices habituels apparaîtront ici.' : 'Pas encore de suggestion pour cette étape.' }}</p>
                    @endforelse
                </div>
            @endforeach
        @endforeach
    </div>
</section>
