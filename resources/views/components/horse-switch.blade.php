{{-- Plusieurs chevaux : passer d'un cheval à l'autre (ou à tous) sans quitter la page (App\Support\HorseSwitch). --}}
@php $switch = \App\Support\HorseSwitch::forCurrentPage(); @endphp
@if ($switch)
    <form method="POST" action="{{ route('focus.set') }}" class="no-scrollbar -mx-4 mb-3 flex gap-2 overflow-x-auto px-4 py-1 sm:mx-0 sm:px-1" aria-label="Choisir le cheval" data-horse-switch>
        @csrf
        <input type="hidden" name="back" value="{{ $switch['route'] }}">
        @foreach (request()->query() as $key => $value)
            @if (is_scalar($value) && ! in_array($key, ['horse', 'cheval', 'page'], true))<input type="hidden" name="query[{{ $key }}]" value="{{ $value }}">@endif
        @endforeach
        <button name="horse" value="all" @if (! $switch['current']) aria-current="true" @endif class="pill min-h-11 shrink-0 gap-2 px-3 text-sm {{ $switch['current'] ? 'bg-white text-slate-700 ring-1 ring-slate-200' : 'bg-brand-50 font-semibold text-brand-900 ring-2 ring-brand-500' }}" data-horse-switch-chip="all"><x-icon name="horse" class="size-4" /> Tous</button>
        @foreach ($switch['horses'] as $h)
            @php $on = $switch['current'] === $h->id; @endphp
            <button name="horse" value="{{ $h->id }}" @if ($on) aria-current="true" @endif class="pill min-h-11 shrink-0 gap-2 pl-1.5 text-sm {{ $on ? 'bg-brand-50 font-semibold text-brand-900 ring-2 ring-brand-500' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}" data-horse-switch-chip="{{ $h->id }}">
                @if ($h->mainPhotoUrl())<img src="{{ $h->mainPhotoUrl() }}" alt="" class="size-8 rounded-full object-cover">@else<span class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-800">{{ mb_strtoupper(mb_substr($h->shortName(), 0, 1)) }}</span>@endif
                {{ $h->shortName() }}
            </button>
        @endforeach
    </form>
    @if ($switch['current'])<script>document.currentScript.previousElementSibling.querySelector('[aria-current]')?.scrollIntoView({ inline: 'center', block: 'nearest' });</script>@endif
@endif
