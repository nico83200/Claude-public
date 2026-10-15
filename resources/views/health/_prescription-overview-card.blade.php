{{-- Page Santé du menu : ordonnance ou compte rendu lu par l'assistant, pour le cheval choisi (voir App\Http\Controllers\PrescriptionController). --}}
@php
    $rxEntitlements = app(\App\Services\Entitlements::class);
    $rxHorses = $editHorses->filter(fn ($h) => $rxEntitlements->hasFeature($h->organization, 'assistant'))->values();
@endphp
@if ($editHorses->isNotEmpty() && app(\App\Services\Assistant\AssistantService::class)->serviceReady())
    <div id="ordonnance" class="card mb-4 flex scroll-mt-24 flex-wrap items-center gap-x-4 gap-y-3 p-4"
         x-data="fileDrop({ horse: @js((string) ($rxHorses->first()?->id ?? '')), actions: @js($rxHorses->mapWithKeys(fn ($h) => [(string) $h->id => route('horses.prescription.read', $h)])) })"
         @dragenter="enter($event)" @dragover="enter($event)" @dragleave="leave($event)" @drop="drop($event)" :class="over && 'ring-2 ring-brand-500 bg-brand-50/60'" data-prescription-overview>
        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-800"><x-icon name="doc" class="size-6" /></span>
        <div class="min-w-[12rem] flex-1">
            <p class="font-semibold text-slate-900">Une ordonnance du vétérinaire ?</p>
            <p class="text-sm text-slate-500" x-show="! busy"><span x-show="! desktop">Photographiez-la</span><span x-show="desktop" x-cloak>Choisissez le fichier ou glissez-le ici</span> : l'assistant prépare le soin, les traitements et le prochain contrôle. Vous vérifiez avant d'enregistrer.</p>
            <p class="text-sm text-brand-800" x-show="busy" x-cloak><span class="thinking-dots"><span>●</span><span>●</span><span>●</span></span> Lecture de l'ordonnance…</p>
        </div>
        @if ($rxHorses->isNotEmpty())
            <form method="POST" :action="actions[horse]" action="{{ route('horses.prescription.read', $rxHorses->first()) }}" enctype="multipart/form-data" class="flex w-full flex-wrap items-end gap-2 sm:w-auto">
                @csrf
                @if ($rxHorses->count() > 1)
                    <div class="min-w-[9rem] flex-1 sm:flex-none">
                        <label class="label" for="rx_horse">Cheval</label>
                        <select id="rx_horse" x-model="horse" class="input" data-native>
                            @foreach ($rxHorses as $h)<option value="{{ $h->id }}">{{ $h->shortName() }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <label class="btn-primary min-h-11 flex-1 cursor-pointer justify-center px-4 sm:flex-none" :class="busy && 'pointer-events-none opacity-60'">
                    <span x-show="! desktop" class="inline-flex items-center gap-2"><x-icon name="camera" class="size-4" /> Photographier</span>
                    <span x-show="desktop" x-cloak class="inline-flex items-center gap-2" data-file-choose><x-icon name="upload" class="size-4" /> Choisir un fichier</span>
                    <input type="file" name="prescription" accept="image/*,application/pdf" class="sr-only" @change="send($el)">
                </label>
            </form>
        @else
            <a href="{{ route('billing.index', ['fonction' => 'assistant']) }}#offres" class="btn-secondary min-h-11 shrink-0 px-4"><x-icon name="camera" class="size-4" /> Photographier <x-locked-mark :org="$editHorses->first()->organization" feature="assistant" /></a>
        @endif
    </div>
@endif
