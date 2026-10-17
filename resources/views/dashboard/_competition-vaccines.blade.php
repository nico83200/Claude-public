{{-- Concours des prochaines semaines dont les vaccins sont à revoir (App\Services\CompetitionVaccines::alerts). --}}
@foreach ($alerts as $a)
    @php
        [$color, $label] = \App\Services\CompetitionVaccines::badge($a['check']);
    @endphp
    <a href="{{ route('calendar.show', $a['event']) }}" class="flex items-center gap-3 border-t border-slate-100 py-2.5 text-sm first:border-0" data-competition-alert="{{ $a['event']->id }}">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $a['check']['state'] === 'unsure' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700' }}"><x-icon name="alert" class="size-4" /></span>
        <span class="min-w-0 flex-1">
            <span class="block font-medium text-slate-900">{{ $label }} · {{ $a['event']->title }}</span>
            <span class="block text-xs text-slate-600">{{ ucfirst($a['event']->starts_at->translatedFormat('l j F')) }}{{ ! empty($showHorse) ? ' · '.$a['event']->horse?->shortName() : '' }} — {{ app(\App\Services\CompetitionVaccines::class)->headline($a['check']) }}</span>
        </span>
        <x-icon name="chevron" class="size-4 shrink-0 text-slate-300" />
    </a>
@endforeach
