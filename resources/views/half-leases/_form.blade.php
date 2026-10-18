{{-- Fiche demi-pension : personne, dates, jours habituels, tarif, droits. $lease (nouvelle ou existante), $taken (jours d'une autre demi-pension), $week (semaine type). --}}
@php
    $days = array_map('intval', old('weekdays', $lease->weekdays ?? []));
    $date = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
@endphp
<x-section title="La personne">
    <div class="grid gap-3 sm:grid-cols-2">
        <x-field name="name" label="Nom" :value="$lease->name" autocomplete="name" required />
        @if ($lease->exists)
            <div><p class="label">E-mail</p><p class="input bg-slate-50 text-slate-600">{{ $lease->email }}</p></div>
        @else
            <x-field name="email" type="email" label="E-mail" help="Compte déjà créé à cette adresse : la proposition arrive dans son appli. Sinon : e-mail d'invitation." autocomplete="email" required />
        @endif
        <x-field name="phone" type="tel" label="Téléphone" :value="$lease->phone" autocomplete="tel" />
    </div>
</x-section>
<x-section title="Dates">
    <div class="grid gap-3 sm:grid-cols-2">
        <x-field name="starts_on" type="date" label="Début" :value="$date($lease->starts_on)" required />
        <x-field name="trial_ends_on" type="date" label="Fin de la période d'essai" :value="$date($lease->trial_ends_on)" help="Rappel aux deux, 2 jours avant." />
        <x-field name="ends_on" type="date" label="Fin prévue" :value="$date($lease->ends_on)" help="Par exemple le 30 juin pour une année scolaire. Vide : sans date de fin." />
        <x-select name="notice_days" label="Préavis" :options="\App\Models\HalfLease::NOTICES" :value="$lease->notice_days" placeholder="Sans préavis" help="Proposé comme date de fin quand l'un de vous y met fin." />
    </div>
</x-section>
<x-section title="Jours habituels" data-half-lease-days>
    <div class="grid grid-cols-7 gap-1.5" role="group" aria-label="Jours habituels">
        @foreach (\App\Models\RidingDay::WEEKDAYS as $num => $dayName)
            @php $other = $taken[$num] ?? null; @endphp
            <label class="flex min-h-14 cursor-pointer flex-col items-center justify-center rounded-2xl px-1 text-center ring-2 ring-slate-200 has-checked:bg-brand-50 has-checked:ring-brand-600 {{ $other ? 'cursor-not-allowed opacity-50' : '' }}" title="{{ $dayName }}">
                <input type="checkbox" name="weekdays[]" value="{{ $num }}" class="sr-only" @checked(in_array($num, $days, true)) @disabled($other)>
                <span class="text-sm font-semibold text-slate-900">{{ mb_substr($dayName, 0, 3) }}</span>
            </label>
        @endforeach
    </div>
    <ul class="mt-2 space-y-0.5 text-xs text-slate-500">
        @foreach ($taken as $num => $who)<li>{{ \App\Models\RidingDay::WEEKDAYS[$num] }} : jour de {{ $who }} (autre demi-pension).</li>@endforeach
        @foreach ($restWeek ?? [] as $num)
            @if (! isset($taken[$num]))<li>{{ \App\Models\RidingDay::WEEKDAYS[$num] }} : jour de repos du cheval ; il deviendra son jour si vous le cochez.</li>@endif
        @endforeach
        @foreach ($week as $num => $who)
            @if (! isset($taken[$num]) && $who->id !== $lease->user_id)<li>{{ \App\Models\RidingDay::WEEKDAYS[$num] }} : aujourd'hui le jour de {{ $who->name }} ; il passera à la demi-pension à son début si vous le cochez.</li>@endif
        @endforeach
    </ul>
    @error('weekdays')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    <p class="mt-2 text-xs text-slate-500">Inscrits dans les jours de monte à partir du début ; chacun pourra laisser un jour à l'autre.</p>
</x-section>
<x-section title="Tarif">
    <div class="grid gap-3 sm:grid-cols-2">
        <x-field name="monthly_amount" type="number" step="0.01" min="0" inputmode="decimal" label="Montant par mois (€)" :value="$lease->monthly_amount" help="0 : demi-pension sans contrepartie financière." />
        <x-field name="payment_day" type="number" min="1" max="28" inputmode="numeric" label="Jour de paiement" :value="$lease->payment_day" help="Par exemple 5 pour le 5 du mois. Vide : le jour du début." />
        <x-select name="first_month" label="Premier mois, si le début tombe après le jour de paiement" :options="\App\Services\HalfLeaseFees::FIRST_MONTH" :value="$lease->first_month ?? 'full'" class="sm:col-span-2" />
    </div>
    <p class="mt-2 text-xs text-slate-500">{{ $horse->organization?->type === 'stable' ? 'Cheval de l\'écurie : la mensualité figure sur le relevé mensuel de la personne, avec ses cours.' : 'Chaque mois, la mensualité arrive dans le budget du cheval (recette « Demi-pension ») ; la demi-pension la voit dans ses frais, vous la notez reçue.' }}</p>
    <div class="mt-3">
        <label for="f_notes" class="label">Conditions particulières</label>
        <textarea id="f_notes" name="notes" rows="3" class="input" placeholder="Ce qui est compris, balades, concours, soins à faire, vacances…">{{ old('notes', $lease->notes) }}</textarea>
    </div>
</x-section>
<x-section title="Accès au dossier du cheval">
    @include('sharing._perms', ['selected' => old('permissions', $lease->permissions ?? $presets['half_lease']), 'prefix' => 'permissions'])
</x-section>
