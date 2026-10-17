@php
    // Nouvel événement : valeurs proposées par le lien d'origine (rappel intelligent « prendre rendez-vous »…).
    $prefillDate = rescue(fn () => request('date') ? \Illuminate\Support\Carbon::parse(request('date')) : null, null, false);
    $e = $event ?? new \App\Models\CalendarEvent([
        'type' => array_key_exists((string) request('type'), \App\Models\CalendarEvent::TYPES) ? request('type') : 'custom',
        'title' => mb_substr((string) request('title'), 0, 150) ?: null,
        'professional_id' => request()->integer('professional') ?: null,
        'starts_at' => ($prefillDate ?? $defaultDate ?? now())->copy()->setTime(9, 0), 'duration_minutes' => 60,
    ]);
@endphp
@php
    // Nouvel événement de type Séance ou Cours : on part directement sur la préparation de séance (cheval, date et heure repris).
    $toSession = ! isset($event) && app(\App\Services\HorseAccess::class)->horseIdsWith(auth()->user(), \App\Support\Perm::SESSIONS_CREATE) !== [];
@endphp
@php
    // Un seul cheval : présélectionné. Titre proposé d'après le type et le cheval tant qu'il n'a pas été modifié.
    $horseList = collect($horses)->all();
    $onlyHorse = ! isset($event) && count($horseList) === 1 ? array_key_first($horseList) : null;
    $hasOptions = filled($e->professional_id) || filled($e->recurrence) || filled($e->notes) || $errors->hasAny(['recurrence', 'recurrence_interval', 'recurrence_until', 'notes']);
    // Concours : règlement des vaccins (celui du dernier concours saisi, sinon celui par défaut).
    $ruleOptions = \App\Support\VaccineRules::options();
    $lastRule = isset($event) ? null : \App\Models\CalendarEvent::where('type', 'competition')->where('created_by', auth()->id())->whereNotNull('vaccine_rules')->latest('id')->value('vaccine_rules');
    $ruleValue = $e->vaccine_rules ?? (isset($ruleOptions[$lastRule]) ? $lastRule : \App\Support\VaccineRules::defaultKey());
@endphp
<div class="grid gap-3 sm:grid-cols-2" @if ($toSession) data-session-redirect @endif
     x-data="{ allDay: @js((bool) old('all_day', $e->all_day)), rec: @js((string) old('recurrence', $e->recurrence)), type: @js((string) old('type', $e->type)), autoTitle: @js(! isset($event) && ! filled(old('title', $e->title))),
        types: @js(\App\Models\CalendarEvent::TYPES), horses: @js($horseList),
        suggest(f) { if (! this.autoTitle) return; const t = this.types[f.type.value] || ''; const h = this.horses[f.horse_id?.value] || ''; f.title.value = f.type.value === 'custom' ? '' : [t, h].filter(Boolean).join(' – '); } }"
     @change="if (['type', 'horse_id'].includes($event.target.name)) suggest($event.target.form);@if ($toSession) if ($event.target.name === 'type' && ['session', 'lesson'].includes($event.target.value)) { const f = $event.target.form; location.href = @js(route('sessions.create')) + '?' + new URLSearchParams({ horse: f.horse_id?.value || '', date: (f.date?.value || '') + (f.time?.value ? ' ' + f.time.value : ''), type: $event.target.value === 'lesson' ? 'private_lesson' : 'free' }); } @endif">
    <x-select name="type" label="Type" :options="\App\Models\CalendarEvent::TYPES" :value="$e->type" required x-model="type" :help="$toSession ? 'Séance ou Cours : la préparation de séance s\'ouvre (exercices, cavalier, bilan).' : null" />
    <x-horse-select name="horse_id" :options="$horses" :value="$e->horse_id ?? request('horse', $onlyHorse)" placeholder="Aucun (événement de l'espace)" />
    <x-field name="title" label="Titre" :value="$e->title" required class="sm:col-span-2" @input="autoTitle = false" />
    <x-field name="date" type="date" label="Date" :value="$e->starts_at->format('Y-m-d')" required />
    <div x-show="! allDay"><x-field name="time" type="time" label="Heure" :value="$e->starts_at->format('H:i')" /></div>
    <div class="flex items-end"><label class="flex min-h-11 items-center gap-3 text-sm"><input type="hidden" name="all_day" value="0"><input type="checkbox" name="all_day" value="1" x-model="allDay" class="size-5 rounded"> Toute la journée</label></div>
    <div x-show="! allDay"><x-field name="duration_minutes" type="number" label="Durée (min)" :value="$e->duration_minutes" min="5" max="1440" /></div>
    <div x-show="type === 'competition'" x-cloak class="sm:col-span-2" data-event-vaccine-rules>
        <x-select name="vaccine_rules" label="Vaccins exigés (règlement)" :options="$ruleOptions" :value="$ruleValue" help="Le carnet de santé du cheval est vérifié pour ce concours : grippe, et rhinopneumonie selon le règlement." />
    </div>
    <div x-show="type === 'competition'" x-cloak data-event-coaching>
        <x-field name="coaching_rounds" type="number" min="1" max="50" label="Parcours coachés (facultatif)" :value="$e->coaching_rounds"
            help="Coaching : choisissez l'enseignant comme intervenant (Plus d'options) ; son tarif au jour ou au parcours est compté une fois le concours validé." />
    </div>
    <details class="group rounded-2xl bg-slate-50 p-3 sm:col-span-2" @if ($hasOptions) open @endif data-event-options>
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between text-sm font-medium text-slate-700">Plus d'options (intervenant, récurrence, observations)<x-icon name="chevron" class="size-4 transition group-open:rotate-90" /></summary>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <x-professional-select :options="$professionals" :value="$e->professional_id" />
            <x-select name="recurrence" label="Récurrence" :options="['daily' => 'Tous les jours', 'weekly' => 'Toutes les semaines', 'monthly' => 'Tous les mois']" :value="$e->recurrence" placeholder="Aucune" x-model="rec" />
            <div x-show="rec" x-cloak><x-field name="recurrence_interval" type="number" label="Tous les (intervalle)" :value="$e->recurrence_interval ?? 1" min="1" max="52" /></div>
            <div x-show="rec" x-cloak><x-field name="recurrence_until" type="date" label="Jusqu'au" :value="$e->recurrence_until?->format('Y-m-d')" /></div>
            <x-textarea name="notes" label="Observations" :value="$e->notes" class="sm:col-span-2" rows="2" />
        </div>
    </details>
</div>
