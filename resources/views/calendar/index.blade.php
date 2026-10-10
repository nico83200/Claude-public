@php
    $prev = match ($view) { 'day' => $date->copy()->subDay(), 'week' => $date->copy()->subWeek(), default => $date->copy()->subMonthNoOverflow() };
    $next = match ($view) { 'day' => $date->copy()->addDay(), 'week' => $date->copy()->addWeek(), default => $date->copy()->addMonthNoOverflow() };
    $q = fn ($d, $v = null) => array_filter(['view' => $v ?? $view, 'date' => $d->toDateString(), 'horse' => request('horse'), 'type' => request('type')]);
    $byDay = $occurrences->groupBy(fn ($o) => $o['at']->toDateString());
    $colors = ['red' => 'bg-red-100 text-red-800', 'amber' => 'bg-amber-100 text-amber-800', 'rose' => 'bg-rose-100 text-rose-800', 'purple' => 'bg-violet-100 text-violet-800', 'emerald' => 'bg-emerald-100 text-emerald-800', 'teal' => 'bg-teal-100 text-teal-800', 'blue' => 'bg-blue-100 text-blue-800', 'lime' => 'bg-lime-100 text-lime-800', 'slate' => 'bg-slate-100 text-slate-700', 'sky' => 'bg-sky-100 text-sky-800'];
    $chip = fn ($ev) => $colors[\App\Models\CalendarEvent::COLORS[$ev->type] ?? 'sky'];
@endphp
<x-layouts.app title="Calendrier">
    <x-page-header title="Calendrier" :subtitle="ucfirst($view === 'month' ? $date->translatedFormat('F Y') : ($view === 'week' ? 'Semaine du '.$from->format('d/m').' au '.$to->format('d/m/Y') : $date->translatedFormat('l j F Y')))">
        @if ($editableHorses->isNotEmpty() || ($currentOrganization && auth()->user()->canInOrg($currentOrganization, 'calendar.edit')))
            <button type="button" class="btn-primary" onclick="document.getElementById('new-event').showModal()"><x-icon name="plus" /> Événement</button>
        @endif
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="flex rounded-lg border border-slate-300 bg-white">
            <a href="{{ route('calendar.index', $q($prev)) }}" class="btn-ghost px-3" aria-label="Précédent"><x-icon name="back" class="size-4" /></a>
            <a href="{{ route('calendar.index', $q(now())) }}" class="btn-ghost px-3 text-sm">Aujourd'hui</a>
            <a href="{{ route('calendar.index', $q($next)) }}" class="btn-ghost px-3" aria-label="Suivant"><x-icon name="chevron" class="size-4" /></a>
        </div>
        <div class="flex rounded-lg border border-slate-300 bg-white text-sm">
            @foreach (['day' => 'Jour', 'week' => 'Semaine', 'month' => 'Mois'] as $v => $label)
                <a href="{{ route('calendar.index', $q($date, $v)) }}" class="px-3 py-2 {{ $view === $v ? 'bg-brand-600 font-semibold text-white' : 'text-slate-600' }} first:rounded-l-lg last:rounded-r-lg">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="hidden" name="view" value="{{ $view }}"><input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <x-select name="horse" :options="$horses" :value="request('horse')" placeholder="Tous les chevaux" onchange="this.form.submit()" aria-label="Cheval" />
            <x-select name="type" :options="\App\Models\CalendarEvent::TYPES" :value="request('type')" placeholder="Tous les types" onchange="this.form.submit()" aria-label="Type" />
        </form>
    </div>

    @if ($view === 'month')
        <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
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
                <p class="mb-1 text-sm font-semibold text-slate-600">{{ ucfirst(\Illuminate\Support\Carbon::parse($day)->translatedFormat('l j F')) }}</p>
                <div class="space-y-1.5">
                    @foreach ($items as $o)
                        <a href="{{ route('calendar.show', $o['event']) }}" class="card flex items-center gap-3 p-3 {{ $o['event']->status === 'cancelled' ? 'opacity-60' : '' }}">
                            <span class="w-14 shrink-0 text-center text-sm font-semibold">{{ $o['event']->all_day ? 'Jour' : $o['at']->format('H:i') }}</span>
                            <span class="min-w-0 flex-1"><span class="block truncate font-medium {{ $o['event']->status === 'cancelled' ? 'line-through' : '' }}">{{ $o['event']->title }}</span><span class="text-xs text-slate-500">{{ $o['event']->horse?->shortName() }} {{ $o['event']->professional ? '· '.$o['event']->professional->fullName() : '' }} {{ $o['event']->recurrence ? '· récurrent' : '' }}</span></span>
                            <span class="chip {{ $chip($o['event']) }}">{{ \App\Models\CalendarEvent::TYPES[$o['event']->type] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <x-empty title="Aucun événement sur cette période" icon="calendar" />
        @endforelse
    </div>

    <dialog id="new-event" class="m-auto w-full max-w-2xl rounded-2xl p-0 backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('calendar.store') }}" class="space-y-4 p-5">
            @csrf
            <input type="hidden" name="return_view" value="{{ $view }}">
            <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Nouvel événement</h2><button type="button" class="btn-ghost px-2" onclick="this.closest('dialog').close()" aria-label="Fermer"><x-icon name="x" /></button></div>
            @include('calendar._form', ['horses' => $editableHorses, 'defaultDate' => $date])
            <button class="btn-primary w-full">Ajouter</button>
        </form>
    </dialog>
    @if ($errors->any())<script>document.getElementById('new-event').showModal()</script>@endif
</x-layouts.app>
