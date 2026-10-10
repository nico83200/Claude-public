<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationSetting;
use App\Models\FeatureFlag;
use App\Services\Audit;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public const KEYS = ['app_name', 'support_email', 'support_phone', 'primary_color', 'logo_url', 'legal_entity', 'legal_address', 'dpo_email', 'hosting_provider', 'subprocessors'];

    public function edit()
    {
        return view('admin.settings', [
            'settings' => collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => ApplicationSetting::get($k)]),
            'flags' => FeatureFlag::orderBy('label')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:60'],
            'support_email' => ['nullable', 'email'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_url' => ['nullable', 'url:https', 'max:500'],
            'legal_entity' => ['nullable', 'string', 'max:200'],
            'legal_address' => ['nullable', 'string', 'max:500'],
            'dpo_email' => ['nullable', 'email'],
            'hosting_provider' => ['nullable', 'string', 'max:500'],
            'subprocessors' => ['nullable', 'string', 'max:3000'],
            'flags' => ['array'],
        ]);
        foreach (self::KEYS as $key) {
            ApplicationSetting::put($key, $data[$key] ?? null);
        }
        foreach (FeatureFlag::all() as $flag) {
            $flag->update(['enabled_globally' => (bool) ($data['flags'][$flag->key] ?? false)]);
            cache()->forget('flag:'.$flag->key);
        }
        Audit::log('admin.settings_updated', null, ['keys' => array_keys($data)]);

        return back()->with('success', 'Paramètres enregistrés.');
    }
}
