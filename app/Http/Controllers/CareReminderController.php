<?php

namespace App\Http\Controllers;

use App\Models\CareReminderRule;
use App\Models\Horse;
use App\Services\Audit;
use App\Services\CareReminders;
use App\Services\CareRhythm;
use App\Support\Perm;
use Illuminate\Http\Request;
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
}
