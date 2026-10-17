{{-- Une ligne « À valider » : rendez-vous passé (valider, pas eu lieu, glisser comme dans l'agenda) ou séance sans bilan. --}}
@php
    $at = $item['at'];
    $chip = '<span class="flex w-12 shrink-0 flex-col items-center rounded-xl bg-slate-50 py-1 leading-tight"><span class="text-[11px] text-slate-500 uppercase">'.e($at->isToday() ? 'Auj.' : ($at->isYesterday() ? 'Hier' : $at->translatedFormat('D'))).'</span><span class="font-semibold text-slate-900">'.$at->format('d').'</span></span>';
@endphp
@if ($item['kind'] === 'event')
    @php
        $ev = $item['event'];
        $date = $item['date'];
        $deletable = \App\Http\Controllers\CalendarController::swipeDeletable($ev);
        $health = in_array($ev->type, \App\Services\ToValidate::HEALTH_TYPES, true) || $ev->source_type;
        $meta = collect([$ev->horse?->shortName(), \App\Models\CalendarEvent::TYPES[$ev->type] ?? null, $at->translatedFormat('j M').($ev->all_day ? '' : ' · '.$at->format('H:i')), $ev->recurrence ? 'série' : null])->filter()->implode(' · ');
    @endphp
    <x-swipe.row :delete-url="$deletable && ! $date ? route('calendar.destroy', $ev) : null" :choices="$deletable && $date ? \App\Http\Controllers\CalendarController::deleteChoices($ev, $date) : []" :event-id="$ev->id" :date="$date"
        :validate-url="route('calendar.done', array_filter([$ev, 'date' => $date]))" :text="'« '.$ev->title.' » supprimé'" :validate-text="'« '.$ev->title.' » validé'" :dismiss="true"
        rounded="" surface="bg-white" :data-todo-event="$ev->id">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-3">
            <a href="{{ route('calendar.show', array_filter([$ev, 'date' => $date])) }}" class="flex min-w-0 flex-1 basis-56 items-center gap-3">
                {!! $chip !!}
                <span class="min-w-0">
                    <span class="flex items-center gap-1.5 font-medium text-slate-900">@if ($health)<x-icon name="health" class="size-4 shrink-0 text-rose-600" />@endif<span class="truncate">{{ $ev->title }}</span></span>
                    <span class="block truncate text-xs text-slate-500">{{ $meta }}</span>
                    @if ($health)<span class="block text-xs text-rose-700">Rejoint le carnet de santé une fois validé</span>@endif
                </span>
            </a>
            <div class="ml-auto flex shrink-0 items-center gap-1">
                <form method="POST" action="{{ route('calendar.cancel', array_filter([$ev, 'date' => $date])) }}">@csrf<input type="hidden" name="stay" value="1"><button class="btn-ghost min-h-11 px-3 text-sm" data-todo-cancel>Pas eu lieu</button></form>
                <form method="POST" action="{{ route('calendar.done', array_filter([$ev, 'date' => $date])) }}">@csrf<input type="hidden" name="stay" value="1"><button class="btn-primary min-h-11 px-3 text-sm" data-todo-done><x-icon name="check" class="size-4" /> Valider</button></form>
            </div>
        </div>
    </x-swipe.row>
@else
    @php
        $s = $item['session'];
        $meta = collect([$s->horse?->shortName(), $s->riderLabel(), $at->translatedFormat('j M').' · '.$at->format('H:i')])->filter()->implode(' · ');
    @endphp
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-3" data-todo-session="{{ $s->id }}">
        <a href="{{ route('sessions.show', $s) }}" class="flex min-w-0 flex-1 basis-56 items-center gap-3">
            {!! $chip !!}
            <span class="min-w-0">
                <span class="flex items-center gap-1.5 font-medium text-slate-900"><x-icon name="session" class="size-4 shrink-0 text-emerald-600" /><span class="truncate">{{ $s->typeLabel() }}</span></span>
                <span class="block truncate text-xs text-slate-500">{{ $meta }}</span>
            </span>
        </a>
        <div class="ml-auto flex shrink-0 items-center gap-1">
            <form method="POST" action="{{ route('sessions.cancel', $s) }}">@csrf<button class="btn-ghost min-h-11 px-3 text-sm" data-todo-session-cancel>Pas faite</button></form>
            <a href="{{ route('sessions.debrief', $s) }}" class="btn-primary min-h-11 px-3 text-sm" data-todo-debrief><x-icon name="check" class="size-4" /> Faire le bilan</a>
        </div>
    </div>
@endif
