<?php

namespace App\Http\Controllers;

use App\Models\DataRequest;
use App\Models\PushSubscription;
use App\Models\UserProfile;
use App\Services\Audit;
use App\Services\WebPush;
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
        ]);
        $emailChanged = strtolower($data['email']) !== $user->email;
        $user->forceFill(['name' => $data['name'], 'email' => strtolower($data['email'])] + ($emailChanged ? ['email_verified_at' => null] : []))->save();
        UserProfile::updateOrCreate(['user_id' => $user->id], [
            'first_name' => $data['first_name'] ?? null, 'last_name' => $data['last_name'] ?? null, 'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null, 'riding_level' => $data['riding_level'] ?? null,
        ]);
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
            Audit::log('user.email_changed', $user);
        }

        return back()->with('success', $emailChanged ? 'Profil mis à jour. Confirmez votre nouvelle adresse email.' : 'Profil mis à jour.');
    }

    public function notifications(Request $request, WebPush $push)
    {
        return view('settings.notifications', [
            'profile' => $request->user()->profile ?? new UserProfile,
            'vapidKey' => $push->publicKey(),
            'devices' => PushSubscription::where('user_id', $request->user()->id)->count(),
        ]);
    }

    public function updateNotifications(Request $request)
    {
        $data = $request->validate([
            'daily_reminder' => ['nullable', 'boolean'],
            'daily_reminder_at' => ['nullable', 'required_if_accepted:daily_reminder', 'date_format:H:i'],
        ]);
        UserProfile::updateOrCreate(['user_id' => $request->user()->id], [
            'email_notifications' => $request->boolean('email_notifications'),
            'debrief_reminders' => $request->boolean('debrief_reminders'),
            'daily_reminder_at' => $request->boolean('daily_reminder') ? $data['daily_reminder_at'] : null,
        ]);

        return back()->with('success', 'Préférences de notification enregistrées.');
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
}
