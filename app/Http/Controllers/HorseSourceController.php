<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\HorseExternalSource;
use App\Support\Perm;

class HorseSourceController extends Controller
{
    public function decide(Horse $horse, HorseExternalSource $source, string $decision)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);
        $source->update(['validation' => $decision === 'confirm' ? 'confirmed' : 'rejected', 'validated_by' => request()->user()->id]);

        return back()->with('success', $decision === 'confirm' ? 'Information confirmée.' : 'Information rejetée.');
    }
}
