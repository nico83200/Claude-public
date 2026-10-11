<?php

namespace App\Http\Controllers;

use App\Models\CareReminderRule;
use App\Models\Horse;
use App\Services\Audit;
use App\Services\CareReminders;
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
}
