<?php

namespace App\Http\Controllers;

use App\Models\DataRequest;
use App\Models\SyncDevice;
use App\Models\UserProfile;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function profile(Request $request)
    {
        return view('settings.profile', ['user' => $request->user(), 'profile' => $request->user()->profile ?? new UserProfile]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'riding_level' => ['nullable', 'string', 'max:60'],
            'email_notifications' => ['nullable', 'boolean'],
        ]);
        $emailChanged = strtolower($data['email']) !== $user->email;
        $user->forceFill(['name' => $data['name'], 'email' => strtolower($data['email'])] + ($emailChanged ? ['email_verified_at' => null] : []))->save();
        UserProfile::updateOrCreate(['user_id' => $user->id], [
            'first_name' => $data['first_name'] ?? null, 'last_name' => $data['last_name'] ?? null, 'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null, 'riding_level' => $data['riding_level'] ?? null, 'email_notifications' => $request->boolean('email_notifications'),
        ]);
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
            Audit::log('user.email_changed', $user);
        }

        return back()->with('success', $emailChanged ? 'Profil mis à jour. Confirmez votre nouvelle adresse email.' : 'Profil mis à jour.');
    }

    public function security(Request $request)
    {
        return view('settings.security', ['user' => $request->user()]);
    }

    public function privacy(Request $request)
    {
        return view('settings.privacy', ['requests' => DataRequest::where('user_id', $request->user()->id)->latest()->get()]);
    }

    public function dataRequest(Request $request)
    {
        $data = $request->validate(['type' => ['required', Rule::in(['rectification', 'deletion'])], 'details' => ['nullable', 'string', 'max:5000']]);
        $user = $request->user();
        DataRequest::create($data + ['user_id' => $user->id, 'email' => $user->email]);
        if ($data['type'] === 'deletion') {
            $user->forceFill(['deletion_requested_at' => now()])->save();
        }
        Audit::log('gdpr.request', $user, ['type' => $data['type']]);

        return back()->with('success', 'Demande enregistrée. Elle sera traitée sous 30 jours maximum ; vous serez informé par email.');
    }

    public function devices(Request $request)
    {
        return view('settings.devices', ['devices' => SyncDevice::where('user_id', $request->user()->id)->latest('last_seen_at')->get()]);
    }

    public function wipeDevice(Request $request, SyncDevice $device)
    {
        abort_unless($device->user_id === $request->user()->id, 404);
        $device->update(['wipe_requested_at' => now()]);

        return back()->with('success', 'Purge demandée : les données locales de cet appareil seront effacées dès sa prochaine connexion.');
    }
}
