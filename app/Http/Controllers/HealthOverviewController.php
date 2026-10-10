<?php

namespace App\Http\Controllers;

use App\Models\CareRecord;
use App\Models\HealthObservation;
use App\Models\Treatment;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\Request;

class HealthOverviewController extends Controller
{
    public function __invoke(Request $request, HorseAccess $access)
    {
        $user = $request->user();
        $healthIds = $access->horseIdsWith($user, Perm::HEALTH_VIEW);
        $treatIds = $access->horseIdsWith($user, Perm::TREATMENTS_VIEW);

        return view('health.overview', [
            'due' => CareRecord::with(['horse', 'category', 'professional'])->whereIn('horse_id', $healthIds)->whereNotNull('next_check_on')
                ->where('next_check_on', '<=', now()->addDays(60)->toDateString())
                ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('care_records as later')->whereColumn('later.horse_id', 'care_records.horse_id')
                    ->whereColumn('later.care_category_id', 'care_records.care_category_id')->whereColumn('later.performed_at', '>', 'care_records.performed_at')->whereNull('later.deleted_at'))
                ->orderBy('next_check_on')->get(),
            'treatments' => Treatment::with(['horse', 'responsible'])->whereIn('horse_id', $treatIds)->current()->orderBy('ends_on')->get(),
            'recent' => CareRecord::with(['horse', 'category'])->whereIn('horse_id', $healthIds)->orderByDesc('performed_at')->limit(15)->get(),
            'alerts' => HealthObservation::with(['horse', 'author'])->whereIn('horse_id', $healthIds)->whereNull('resolved_at')->where('severity', '!=', 'info')->latest('observed_at')->get(),
        ]);
    }
}
