<x-layouts.app :title="'Jours de monte · '.$horse->shortName()">
    <x-horse-header :horse="$horse" />
    @php
        $me = auth()->user();
        $options = $candidates->mapWithKeys(fn ($u) => [$u->id => $u->id === $me->id ? $u->name.' (moi)' : $u->name]);
    @endphp
    {{-- Demandes de jour : à accepter d'un geste (personne du jour, ou propriétaire pour un jour libre) ; les siennes en attente. --}}
    @if ($toAnswer->isNotEmpty() || $myRequests->isNotEmpty())
        <div class="mb-4 space-y-2" data-riding-requests>
            @foreach ($toAnswer as $r)
                <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm" data-riding-request="{{ $r->id }}">
                    <p><strong>{{ $r->requester->name }}</strong> aimerait monter {{ $r->date->translatedFormat('l j F') }}{{ $r->swap_date ? ', en échange de son '.$r->swap_date->translatedFormat('l j F') : '' }}.</p>
                    <div class="mt-3 flex gap-2">
                        <form method="POST" action="{{ route('horses.riding-days.answer', [$horse, $r]) }}" class="flex-1">@csrf<input type="hidden" name="decision" value="accept"><button class="btn-primary min-h-11 w-full justify-center" data-riding-request-accept>{{ $r->swap_date ? 'Accepter l\'échange' : 'Laisser ce jour' }}</button></form>
                        <form method="POST" action="{{ route('horses.riding-days.answer', [$horse, $r]) }}" class="flex-1">@csrf<input type="hidden" name="decision" value="decline"><button class="btn-secondary min-h-11 w-full justify-center">Refuser</button></form>
                    </div>
                </div>
            @endforeach
            @foreach ($myRequests as $r)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-slate-50 p-3 text-sm" data-my-riding-request>
                    <span>Votre demande pour {{ $r->date->translatedFormat('l j F') }}{{ $r->swap_date ? ' (contre '.$r->swap_date->translatedFormat('l j F').')' : '' }} : en attente de {{ $r->holder?->name ?? 'la réponse du propriétaire' }}.</span>
                    <form method="POST" action="{{ route('horses.riding-days.cancel-request', [$horse, $r]) }}">@csrf @method('DELETE')<button class="btn-ghost min-h-10 px-2 text-sm">Retirer</button></form>
                </div>
            @endforeach
        </div>
    @endif
    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
        <x-section title="Semaine type" data-riding-week>
            <p class="mb-3 text-sm text-slate-600">À qui revient chaque jour : il s'affiche dans l'agenda et sur l'accueil, et une séance prévue le jour d'un autre le prévient.</p>
            @if ($canManage)
                <form method="POST" action="{{ route('horses.riding-days.update', $horse) }}" class="grid gap-3 sm:grid-cols-2" data-riding-week-form>
                    @csrf @method('PUT')
                    @foreach (\App\Models\RidingDay::WEEKDAYS as $num => $dayName)
                        <x-select :name="'days['.$num.']'" :label="$dayName" :options="$options" :value="($weekly[$num] ?? null)?->id" placeholder="Libre" />
                    @endforeach
                    <div class="sm:col-span-2">
                        <button class="btn-primary w-full justify-center sm:w-auto" data-riding-week-save>Enregistrer</button>
                        @if ($candidates->count() < 2)<p class="mt-2 text-xs text-slate-500">Partagez {{ $horse->shortName() }} avec une demi-pension ou un cavalier pour lui attribuer des jours.</p>@endif
                    </div>
                </form>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach (\App\Models\RidingDay::WEEKDAYS as $num => $dayName)
                        @php $u = $weekly[$num] ?? null; @endphp
                        <li class="flex justify-between py-2 {{ $u?->id === $me->id ? 'font-semibold text-brand-900' : '' }}"><span>{{ $dayName }}</span><span class="{{ $u ? '' : 'text-slate-400' }}">{{ $u ? ($u->id === $me->id ? 'Moi' : $u->name) : 'Libre' }}</span></li>
                    @endforeach
                </ul>
            @endif
        </x-section>

        <x-section title="Les 4 prochaines semaines" data-riding-upcoming>
            @forelse ($upcoming as $date => $byHorse)
                @php
                    $info = $byHorse[$horse->id];
                    $u = $info['user'];
                    $day = \Illuminate\Support\Carbon::parse($date);
                    $mine = $u?->id === $me->id;
                    $usual = $weekly[$day->isoWeekday()] ?? null;
                    $others = $options->except($u?->id);
                @endphp
                <div class="border-t border-slate-100 py-2.5 first:border-0" x-data="{ open: false }" data-riding-date="{{ $date }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="w-28 shrink-0 text-sm font-medium text-slate-700">{{ $day->isToday() ? 'Aujourd\'hui' : ucfirst($day->translatedFormat('D j M')) }}</span>
                        <span class="min-w-28 flex-1 text-sm {{ $mine ? 'font-semibold text-brand-900' : ($u ? 'text-slate-900' : 'text-slate-400') }}">{{ $u ? ($mine ? 'Moi' : $u->name) : 'Libre' }}
                            @if ($info['changed'])<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">échange{{ $usual && $usual->id !== $u?->id ? ' · habituellement '.($usual->id === $me->id ? 'moi' : $usual->name) : '' }}</span>@endif
                        </span>
                        @if ($mine || $canManage)
                            <button type="button" class="btn-ghost min-h-11 px-3 text-sm" @click="open = ! open" :aria-expanded="open" data-riding-give-open>{{ $mine ? 'Laisser ce jour' : 'Changer' }}</button>
                        @elseif ($canRide && $myRequests->contains(fn ($r) => $r->date->toDateString() === $date))
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">Demandé</span>
                        @elseif ($canRide)
                            <button type="button" class="btn-ghost min-h-11 px-3 text-sm" @click="open = ! open" :aria-expanded="open" data-riding-ask-open>Demander ce jour</button>
                        @endif
                    </div>
                    @if (! $mine && ! $canManage && $canRide)
                        <form x-show="open" x-cloak method="POST" action="{{ route('horses.riding-days.request', $horse) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-2xl bg-slate-50 p-3" data-riding-ask>
                            @csrf
                            <input type="hidden" name="date" value="{{ $date }}">
                            @if ($u && $myDays->except($date)->isNotEmpty())
                                <x-select name="swap_date" :id="'swap_'.$date" label="En échange de (facultatif)" :options="$myDays->except($date)" placeholder="Sans échange" class="min-w-48 flex-1" />
                            @endif
                            <button class="btn-primary min-h-11">Demander à {{ $u ? $u->name : 'au propriétaire' }}</button>
                        </form>
                    @endif
                    @if ($mine || $canManage)
                        <form x-show="open" x-cloak method="POST" action="{{ route('horses.riding-days.give', $horse) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-2xl bg-slate-50 p-3" data-riding-give>
                            @csrf
                            <input type="hidden" name="date" value="{{ $date }}">
                            <x-select name="to" :id="'to_'.$date" :label="'Le '.$day->translatedFormat('l j F').', monte :'" :options="$others" placeholder="Personne (jour libre)" class="min-w-48 flex-1" />
                            <button class="btn-primary min-h-11">Valider</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucun jour attribué pour l'instant{{ $canManage ? ' : choisissez la semaine type.' : '.' }}</p>
            @endforelse
            <p class="mt-3 text-xs text-slate-500">La personne qui reçoit le jour est prévenue ; un jour laissé libre par une demi-pension est signalé au propriétaire.</p>
        </x-section>

        {{-- Ce mois-ci : jours prévus et séances notées, par personne. --}}
        @if ($month)
            <x-section :title="ucfirst(today()->translatedFormat('F')).' : jours et séances'" data-riding-month>
                @foreach ($month as $row)
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-2 text-sm first:border-0">
                        <span class="font-medium {{ $row['user']->id === $me->id ? 'text-brand-900' : 'text-slate-900' }}">{{ $row['user']->id === $me->id ? 'Moi' : $row['user']->name }}</span>
                        <span class="text-slate-600">{{ \App\Support\Fr::count($row['planned'], 'jour prévu', 'jours prévus') }} · {{ \App\Support\Fr::count($row['sessions'], 'séance notée', 'séances notées') }}</span>
                    </div>
                @endforeach
            </x-section>
        @endif

        {{-- Absence : tous ses jours de la période, libérés ou laissés à quelqu'un, en une fois. --}}
        @if ($canRide)
            <x-section title="Absence" data-riding-absence>
                <form method="POST" action="{{ route('horses.riding-days.absence', $horse) }}" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <x-field name="from" type="date" label="Du" :min="today()->format('Y-m-d')" required />
                    <x-field name="to" type="date" label="Au" :min="today()->format('Y-m-d')" required />
                    <x-select name="give_to" label="Mes jours sur la période" :options="$options->except($me->id)" placeholder="Laissés libres" class="sm:col-span-2" />
                    <button class="btn-secondary min-h-11 sm:col-span-2">Libérer mes jours</button>
                </form>
                <p class="mt-2 text-xs text-slate-500">Vacances, blessure, cheval au repos… : les personnes concernées sont prévenues en une seule fois.</p>
            </x-section>
        @endif
    </div>
</x-layouts.app>
