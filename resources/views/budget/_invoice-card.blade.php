{{-- Facture photographiée lue par l'assistant (voir App\Http\Controllers\InvoiceController) --}}
@php
    $invOrg = $contextHorse?->organization ?? $currentOrganization;
    $assistantReady = app(\App\Services\Assistant\AssistantService::class)->serviceReady();
    $assistantIncluded = $invOrg && app(\App\Services\Entitlements::class)->hasFeature($invOrg, 'assistant');
@endphp
@if ($assistantReady)
    <div id="facture" class="card mb-5 flex scroll-mt-24 items-center gap-4 p-4" x-data="fileDrop" @dragenter="enter($event)" @dragover="enter($event)" @dragleave="leave($event)" @drop="drop($event)" :class="over && 'ring-2 ring-brand-500 bg-brand-50/60'" data-invoice-card>
        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-800"><x-icon name="receipt" class="size-6" /></span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-slate-900">Une facture à noter ?</p>
            <p class="text-sm text-slate-500" x-show="! busy"><span x-show="! desktop">Photographiez-la</span><span x-show="desktop" x-cloak>Choisissez le fichier ou glissez-le ici</span> : l'assistant la lit et prépare la dépense{{ $contextHorse ? '' : ' et le soin' }}.</p>
            <p class="text-sm text-brand-800" x-show="busy" x-cloak><span class="thinking-dots"><span>●</span><span>●</span><span>●</span></span> Lecture de la facture…</p>
        </div>
        @if ($assistantIncluded)
            <form method="POST" action="{{ route('budget.invoice.read') }}" enctype="multipart/form-data">
                @csrf
                @if ($contextHorse)<input type="hidden" name="horse" value="{{ $contextHorse->id }}">@endif
                <label class="btn-primary min-h-11 cursor-pointer px-4" :class="busy && 'pointer-events-none opacity-60'">
                    <span x-show="! desktop" class="inline-flex items-center gap-2"><x-icon name="camera" class="size-4" /> Photographier</span>
                    <span x-show="desktop" x-cloak class="inline-flex items-center gap-2" data-file-choose><x-icon name="upload" class="size-4" /> Choisir un fichier</span>
                    <input type="file" name="invoice" accept="image/*,application/pdf" class="sr-only" @change="send($el)">
                </label>
            </form>
        @else
            <a href="{{ route('billing.index', ['fonction' => 'assistant']) }}#offres" class="btn-secondary min-h-11 shrink-0 px-4"><x-icon name="camera" class="size-4" /> Photographier <x-locked-mark :org="$invOrg" feature="assistant" /></a>
        @endif
    </div>
@endif
