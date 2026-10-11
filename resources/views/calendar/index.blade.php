@php
    $prev = match ($view) { 'day' => $date->copy()->subDay(), 'week' => $date->copy()->subWeek(), default => $date->copy()->subMonthNoOverflow() };
    $next = match ($view) { 'day' => $date->copy()->addDay(), 'week' => $date->copy()->addWeek(), default => $date->copy()->addMonthNoOverflow() };
    $q = fn ($d, $v = null) => array_filter(['view' => $v ?? $view, 'date' => $d->toDateString(), 'horse' => request('horse'), 'type' => request('type')]);
    $byDay = $occurrences->groupBy(fn ($o) => $o['at']->toDateString());
    $colors = ['red' => 'bg-red-100 text-red-800', 'amber' => 'bg-amber-100 text-amber-800', 'rose' => 'bg-rose-100 text-rose-800', 'purple' => 'bg-violet-100 text-violet-800', 'emerald' => 'bg-emerald-100 text-emerald-800', 'teal' => 'bg-teal-100 text-teal-800', 'blue' => 'bg-blue-100 text-blue-800', 'lime' => 'bg-lime-100 text-lime-800', 'slate' => 'bg-slate-100 text-slate-700', 'sky' => 'bg-sky-100 text-sky-800'];
    $dots = ['red' => 'bg-red-500', 'amber' => 'bg-amber-500', 'rose' => 'bg-rose-500', 'purple' => 'bg-violet-500', 'emerald' => 'bg-emerald-500', 'teal' => 'bg-teal-500', 'blue' => 'bg-blue-500', 'lime' => 'bg-lime-500', 'slate' => 'bg-slate-400', 'sky' => 'bg-sky-500'];
    $dot = fn ($ev) => $dots[\App\Models\CalendarEvent::COLORS[$ev->type] ?? 'sky'];
    $chip = fn ($ev) => $colors[\App\Models\CalendarEvent::COLORS[$ev->type] ?? 'sky'];
@endphp
<x-layouts.app title="Calendrier">
    <x-page-header title="Calendrier" :subtitle="ucfirst($view === 'month' ? $date->translatedFormat('F Y') : ($view === 'week' ? 'Semaine du '.$from->format('d/m').' au '.$to->format('d/m/Y') : $date->translatedFormat('l j F Y')))">
        @if ($editableHorses->isNotEmpty() || ($currentOrganization && auth()->user()->canInOrg($currentOrganization, 'calendar.edit')))
            <button type="button" class="btn-primary" onclick="document.getElementById('new-event').showModal()"><x-icon name="plus" /> Événement</button>
        @endif
    </x-page-header>

    <div class="mb-3 flex flex-wrap items-center gap-2">
        <div class="flex shrink-0 items-center rounded-full bg-white p-1 shadow-sm ring-1 ring-slate-200/70">
            <a href="{{ route('calendar.index', $q($prev)) }}" class="flex size-9 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100" aria-label="Précédent"><x-icon name="back" class="size-4" /></a>
            <a href="{{ route('calendar.index', $q(now())) }}" class="rounded-full px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Aujourd'hui</a>
            <a href="{{ route('calendar.index', $q($next)) }}" class="flex size-9 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100" aria-label="Suivant"><x-icon name="chevron" class="size-4" /></a>
        </div>
        <div class="ml-auto flex shrink-0 items-center rounded-full bg-white p-1 text-sm shadow-sm ring-1 ring-slate-200/70">
            @foreach (['day' => 'Jour', 'week' => 'Sem.', 'month' => 'Mois'] as $v => $label)
                <a href="{{ route('calendar.index', $q($date, $v)) }}" @if ($view === $v) aria-current="page" @endif class="rounded-full px-3 py-1.5 font-medium {{ $view === $v ? 'bg-brand-800 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <x-filters :fields="['horse', 'type']" :keep="['view' => $view, 'date' => $date->toDateString()]">
        <x-select name="horse" label="Cheval" :options="$horses" :value="request('horse')" placeholder="Tous" />
        <x-select name="type" label="Type" :options="\App\Models\CalendarEvent::TYPES" :value="request('type')" placeholder="Tous" />
    </x-filters>

    @if ($view === 'month')
        <div class="card hidden overflow-hidden md:block">
            <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-xs font-semibold text-slate-500 uppercase">@foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $d)<div class="py-2">{{ $d }}</div>@endforeach</div>
            <div class="grid grid-cols-7">
                @for ($d = $from->copy(); $d->lte($to); $d->addDay())
                    <div class="min-h-28 border-r border-b border-slate-100 p-1.5 {{ $d->month !== $date->month ? 'bg-slate-50/60 text-slate-400' : '' }}">
                        <a href="{{ route('calendar.index', $q($d, 'day')) }}" class="text-xs font-semibold {{ $d->isToday() ? 'rounded-full bg-brand-600 px-1.5 text-white' : '' }}">{{ $d->day }}</a>
                        @foreach (($byDay[$d->toDateString()] ?? collect())->take(4) as $o)
                            <a href="{{ route('calendar.show', $o['event']) }}" class="mt-1 block truncate rounded px-1 text-[11px] {{ $chip($o['event']) }} {{ $o['event']->status === 'cancelled' ? 'line-through opacity-60' : '' }}">{{ $o['event']->all_day ? '' : $o['at']->format('H:i') }} {{ $o['event']->title }}</a>
                        @endforeach
                        @if (($byDay[$d->toDateString()] ?? collect())->count() > 4)<span class="text-[11px] text-slate-500">+{{ $byDay[$d->toDateString()]->count() - 4 }}</span>@endif
                    </div>
                @endfor
            </div>
        </div>
    @endif

    {{-- Liste (mobile en vue mois, et vues jour/semaine) --}}
    <div class="{{ $view === 'month' ? 'md:hidden' : '' }} space-y-3">
        @forelse ($byDay as $day => $items)
            <div>
                @php $dayC = \Illuminate\Support\Carbon::parse($day); @endphp
                <p class="mb-2 text-sm font-semibold text-slate-500">{{ $dayC->isToday() ? 'Aujourd\'hui' : ($dayC->isTomorrow() ? 'Demain' : ucfirst($dayC->translatedFormat('l j F'))) }}</p>
                <div class="card divide-y divide-slate-100">
                    @foreach ($items as $o)
                        @php $ev = $o['event']; @endphp
                        <a href="{{ route('calendar.show', $ev) }}" class="flex items-center gap-3 p-4 first:rounded-t-3xl last:rounded-b-3xl hover:bg-slate-50 {{ $ev->status === 'cancelled' ? 'opacity-60' : '' }}">
                            <span class="w-12 shrink-0 text-sm font-semibold text-slate-900">{{ $ev->all_day ? 'Jour' : $o['at']->format('H:i') }}</span>
                            <span class="size-2.5 shrink-0 rounded-full {{ $dot($ev) }}" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-slate-900 {{ $ev->status === 'cancelled' ? 'line-through' : '' }}">{{ $ev->title }}</span>
                                <span class="block text-xs text-slate-500">{{ collect([\App\Models\CalendarEvent::TYPES[$ev->type] ?? null, $ev->horse?->shortName(), $ev->professional?->fullName(), $ev->recurrence ? 'récurrent' : null])->filter()->implode(' · ') }}</span>
                            </span>
                            <x-icon name="chevron" class="size-4 shrink-0 text-slate-300" />
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <x-empty title="Aucun événement sur cette période" icon="calendar" />
        @endforelse
    </div>

    <dialog id="new-event" class="m-auto w-[calc(100%-2rem)] max-w-2xl rounded-3xl p-0 backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('calendar.store') }}" class="space-y-4 p-5">
            @csrf
            <input type="hidden" name="return_view" value="{{ $view }}">
            <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Nouvel événement</h2><button type="button" class="btn-ghost px-2" onclick="this.closest('dialog').close()" aria-label="Fermer"><x-icon name="x" /></button></div>
            @include('calendar._form', ['horses' => $editableHorses, 'defaultDate' => $date])
            <button class="btn-primary w-full">Ajouter</button>
        </form>
    </dialog>
    @if ($errors->any() || request()->boolean('nouveau'))<script>document.getElementById('new-event').showModal()</script>@endif
</x-layouts.app>
