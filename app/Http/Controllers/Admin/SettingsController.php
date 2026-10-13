<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationSetting;
use App\Models\FeatureFlag;
use App\Services\Audit;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public const KEYS = ['app_name', 'support_email', 'support_phone', 'primary_color', 'logo_url', 'legal_entity', 'legal_address', 'dpo_email', 'hosting_provider', 'subprocessors', 'inactivity_hours', 'inactivity_message', 'monitor_enabled', 'monitor_cron_minutes', 'monitor_error_threshold'];

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
            'inactivity_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'inactivity_message' => ['nullable', 'string', 'max:200'],
            'monitor_enabled' => ['nullable', 'boolean'],
            'monitor_cron_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'monitor_error_threshold' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'flags' => ['array'],
        ]);
        $data['inactivity_hours'] = (int) ($data['inactivity_hours'] ?? 0);
        $data['monitor_enabled'] = $request->boolean('monitor_enabled');
        $data['monitor_cron_minutes'] = (int) ($data['monitor_cron_minutes'] ?? 15) ?: 15;
        $data['monitor_error_threshold'] = (int) ($data['monitor_error_threshold'] ?? 20) ?: 20;
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
