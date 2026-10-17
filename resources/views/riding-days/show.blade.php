<x-layouts.app :title="'Jours de monte · '.$horse->shortName()">
    <x-horse-header :horse="$horse" />
    @php
        $me = auth()->user();
        $options = $candidates->mapWithKeys(fn ($u) => [$u->id => $u->id === $me->id ? $u->name.' (moi)' : $u->name]);
    @endphp
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
                        <span class="min-w-0 flex-1 text-sm {{ $mine ? 'font-semibold text-brand-900' : ($u ? 'text-slate-900' : 'text-slate-400') }}">{{ $u ? ($mine ? 'Moi' : $u->name) : 'Libre' }}
                            @if ($info['changed'])<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">échange{{ $usual && $usual->id !== $u?->id ? ' · habituellement '.($usual->id === $me->id ? 'moi' : $usual->name) : '' }}</span>@endif
                        </span>
                        @if ($mine || $canManage)
                            <button type="button" class="btn-ghost min-h-11 px-3 text-sm" @click="open = ! open" :aria-expanded="open" data-riding-give-open>{{ $mine ? 'Laisser ce jour' : 'Changer' }}</button>
                        @endif
                    </div>
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
    </div>
</x-layouts.app>
