<?php

namespace App\Http\Controllers;

use App\Models\CareRecord;
use App\Models\CareReminderRule;
use App\Models\Horse;
use App\Services\Audit;
use App\Services\CareCalendarSync;
use App\Services\CareReminders;
use App\Services\CareRhythm;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Rappels de suivi d'un cheval : fréquence maréchal, vaccination, vermifuge… */
class CareReminderController extends Controller
{
    public function update(Request $request, Horse $horse, CareReminders $reminders)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $rules = $reminders->rulesFor($horse);
        $data = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.enabled' => ['boolean'],
            'rules.*.interval_value' => ['required', 'integer', 'min:1', 'max:120'],
            'rules.*.interval_unit' => ['required', Rule::in(array_keys(CareReminderRule::UNITS))],
            'rules.*.notice_days' => ['required', 'integer', 'min:0', 'max:60'],
        ], ['rules.*.interval_value.required' => 'Indiquez la fréquence.']);

        foreach ($data['rules'] as $categoryId => $values) {
            if (! $rules->has((int) $categoryId)) {
                continue;
            }
            $reminders->save($horse, (int) $categoryId, ['enabled' => (bool) ($values['enabled'] ?? false)] + array_intersect_key($values, array_flip(['interval_value', 'interval_unit', 'notice_days'])));
        }
        Audit::log('care.reminders_updated', $horse, [], $horse->organization_id);

        return back()->with('success', 'Rappels de suivi enregistrés.');
    }

    /** Rappel intelligent : la fréquence du rappel prend le rythme réellement observé. */
    public function adoptRhythm(Request $request, Horse $horse, CareReminders $reminders, CareRhythm $rhythm)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $categoryId = (int) $request->validate(['category_id' => ['required', 'integer']])['category_id'];
        $row = $rhythm->forHorse($horse)->first(fn ($r) => $r['category']->id === $categoryId) ?? abort(404);
        $days = $row['interval_days'];
        [$value, $unit] = $days <= 112 ? [max(1, (int) round($days / 7)), 'weeks'] : [max(1, (int) round($days / 30.44)), 'months'];
        $current = $reminders->ruleFor($horse, $categoryId);
        $reminders->save($horse, $categoryId, ['enabled' => true, 'interval_value' => $value, 'interval_unit' => $unit, 'notice_days' => $current['notice_days'] ?? 7]);
        Audit::log('care.rhythm_adopted', $horse, ['category' => $categoryId, 'days' => $days], $horse->organization_id);

        return back()->with('success', $row['category']->name.' : rappel réglé sur '.CareReminders::describe($value, $unit).', votre rythme habituel.');
    }

    /** Prochain contrôle d'un soin ajouté à l'agenda, à sa date d'échéance (seulement la prochaine échéance du type de soin). */
    public function planCheck(Request $request, Horse $horse, CareRecord $care, CareCalendarSync $sync)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $latest = $horse->careRecords()->where('care_category_id', $care->care_category_id)->latest('performed_at')->value('id');
        abort_unless($care->next_check_on && ! $care->next_check_done_at && $latest === $care->id, 422, 'Ce contrôle n\'est plus la prochaine échéance.');
        // Jour et heure du rendez-vous choisis (facultatif) ; à défaut, la date d'échéance à 9 h.
        $data = $request->validate(['date' => ['nullable', 'date', 'after_or_equal:today'], 'time' => ['nullable', 'date_format:H:i']]);
        $at = ! empty($data['date']) ? Carbon::parse($data['date'].' '.($data['time'] ?? '09:00')) : null;
        $event = $sync->planCheck($care, $at);
        Audit::log('care.check_planned', $care, ['event' => $event?->id], $horse->organization_id);
        $hour = $event->starts_at->format('H:i') === '09:00' && ! $at ? ' à 9 h : vous pouvez changer l\'heure dans l\'agenda.' : ' à '.$event->starts_at->format('H:i').'.';

        return back()->with('success', 'Rendez-vous ajouté à l\'agenda le '.$event->starts_at->translatedFormat('j F Y').$hour);
    }
}
