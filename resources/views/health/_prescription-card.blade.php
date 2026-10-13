{{-- Ordonnance ou compte rendu photographié, lu par l'assistant (voir App\Http\Controllers\PrescriptionController) --}}
@php
    $rxReady = app(\App\Services\Assistant\AssistantService::class)->serviceReady();
    $rxIncluded = app(\App\Services\Entitlements::class)->hasFeature($horse->organization, 'assistant');
@endphp
@if ($rxReady)
    <div class="card mb-4 flex items-center gap-4 p-4" x-data="{ busy: false }" data-prescription-card>
        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-800"><x-icon name="doc" class="size-6" /></span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-slate-900">Une ordonnance du vétérinaire ?</p>
            <p class="text-sm text-slate-500" x-show="! busy">Photographiez-la : l'assistant prépare le soin, les traitements et le prochain contrôle. Vous vérifiez avant d'enregistrer.</p>
            <p class="text-sm text-brand-800" x-show="busy" x-cloak><span class="thinking-dots"><span>●</span><span>●</span><span>●</span></span> Lecture de l'ordonnance…</p>
        </div>
        @if ($rxIncluded)
            <form method="POST" action="{{ route('horses.prescription.read', $horse) }}" enctype="multipart/form-data" @submit="busy = true">
                @csrf
                <label class="btn-primary min-h-11 cursor-pointer px-4" :class="busy && 'pointer-events-none opacity-60'">
                    <x-icon name="camera" class="size-4" /> Photographier
                    <input type="file" name="prescription" accept="image/*,application/pdf" class="sr-only" @change="$el.form.requestSubmit()">
                </label>
            </form>
        @else
            <a href="{{ route('billing.index', ['fonction' => 'assistant']) }}#offres" class="btn-secondary min-h-11 shrink-0 px-4"><x-icon name="camera" class="size-4" /> Photographier <x-locked-mark :org="$horse->organization" feature="assistant" /></a>
        @endif
    </div>
@endif
