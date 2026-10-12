<?php

namespace App\Http\Controllers;

use App\Models\DataRequest;
use App\Models\PushSubscription;
use App\Models\SocialAccount;
use App\Models\UserProfile;
use App\Services\Audit;
use App\Services\Auth\OidcProvider;
use App\Services\WebPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
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
            'dailyStatus' => app(\App\Services\NotificationDiagnostics::class)->dailyReminder($request->user()->loadMissing('profile')),
        ]);
    }

    /** Activation en un geste depuis la suggestion (19 h par défaut, modifiable ensuite). */
    public function enableDailyReminder(Request $request)
    {
        $data = $request->validate(['daily_reminder_at' => ['nullable', 'date_format:H:i']]);
        UserProfile::updateOrCreate(['user_id' => $request->user()->id], ['daily_reminder_at' => $data['daily_reminder_at'] ?? '19:00']);

        return redirect()->route('settings.notifications')->with('success', 'Rappel quotidien activé à '.($data['daily_reminder_at'] ?? '19:00').' : vous pouvez changer l\'heure ci-dessous.');
    }

    public function stopDailyNudge(Request $request)
    {
        UserProfile::updateOrCreate(['user_id' => $request->user()->id], [])->forceFill(['daily_nudge_stopped_at' => now()])->save();

        return redirect()->route('settings.notifications')->with('success', 'C\'est noté : nous ne vous proposerons plus le rappel quotidien. Vous pouvez l\'activer ici à tout moment.');
    }

    public function updateNotifications(Request $request)
    {
        $data = $request->validate([
            'daily_reminder' => ['nullable', 'boolean'],
            'daily_reminder_at' => ['nullable', 'required_if_accepted:daily_reminder', 'date_format:H:i'],
        ]);
        // Désactivé volontairement : la suggestion d'activer le rappel quotidien n'est plus envoyée.
        $previous = $request->user()->profile;
        if ($previous?->daily_reminder_at && ! $request->boolean('daily_reminder')) {
            $previous->forceFill(['daily_nudge_stopped_at' => now()])->save();
        }
        UserProfile::updateOrCreate(['user_id' => $request->user()->id], [
            'email_notifications' => $request->boolean('email_notifications'),
            'debrief_reminders' => $request->boolean('debrief_reminders'),
            'daily_reminder_at' => $request->boolean('daily_reminder') ? $data['daily_reminder_at'] : null,
        ]);

        return back()->with('success', 'Préférences de notification enregistrées.');
    }

    /** Apparence : clair (défaut), sombre ou automatique (suit le réglage du téléphone). */
    public function updateTheme(Request $request)
    {
        $data = $request->validate(['theme' => ['required', Rule::in(['light', 'dark', 'auto'])]]);
        UserProfile::updateOrCreate(['user_id' => $request->user()->id], ['theme' => $data['theme']]);

        return $request->expectsJson() ? response()->json(['theme' => $data['theme']]) : back()->with('success', 'Apparence enregistrée.');
    }

    public function security(Request $request)
    {
        $user = $request->user();
        $linked = $user->socialAccounts()->get()->keyBy('provider');
        $providers = array_values(array_filter(array_keys(SocialAccount::PROVIDERS), fn ($p) => OidcProvider::configured($p) || $linked->has($p)));

        return view('settings.security', ['user' => $user, 'linked' => $linked, 'socialProviders' => $providers]);
    }

    /** Compte créé avec Google / Apple : lien par email pour définir un premier mot de passe. */
    public function sendPasswordLink(Request $request)
    {
        $status = Password::sendResetLink(['email' => $request->user()->email]);

        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', $status === Password::RESET_LINK_SENT
            ? 'Un lien pour définir votre mot de passe a été envoyé à '.$request->user()->email.'.'
            : 'Merci de patienter avant de demander un nouveau lien.');
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
