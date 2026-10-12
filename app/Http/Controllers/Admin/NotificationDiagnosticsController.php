<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\NotificationDiagnostics;
use App\Services\WebPush;
use Illuminate\Http\Request;

/** Pourquoi une notification n'arrive pas : cron, file d'attente, appareils abonnés, erreurs des services push. */
class NotificationDiagnosticsController extends Controller
{
    public function index(Request $request, NotificationDiagnostics $diag, WebPush $push)
    {
        $target = $request->filled('email') ? User::where('email', $request->query('email'))->first() : $request->user();

        return view('admin.notifications', [
            'scheduler' => $diag->scheduler(),
            'queue' => $diag->queue(),
            'vapidOk' => rescue(fn () => (bool) $push->publicKey(), false, false),
            'totals' => [
                'devices' => PushSubscription::count(),
                'users' => PushSubscription::distinct('user_id')->count('user_id'),
                'failing' => PushSubscription::whereNotNull('last_error')->count(),
            ],
            'pushErrors' => PushSubscription::with('user')->whereNotNull('last_error')->latest('last_failure_at')->limit(10)->get(),
            'target' => $target,
            'targetDevices' => $target ? PushSubscription::where('user_id', $target->id)->latest('updated_at')->get() : collect(),
            'daily' => $target ? $diag->dailyReminder($target->loadMissing('profile')) : null,
            'lastNotifications' => $target ? $target->notifications()->latest()->limit(5)->get() : collect(),
        ]);
    }

    /** Envoie une notification d'essai aux appareils d'un utilisateur et affiche la réponse du service push. */
    public function test(Request $request, WebPush $push)
    {
        $user = $request->filled('user_id') ? User::findOrFail($request->integer('user_id')) : $request->user();
        $reached = $push->send($user, (new AppNotification('push_test', 'Jack&Cie', 'Essai de notification depuis l\'administration.', route('dashboard')))->toWebPush($user));
        $devices = PushSubscription::where('user_id', $user->id)->get();
        if (! $devices->count()) {
            return back()->with('error', 'Aucun appareil abonné pour '.$user->email.' : sur le téléphone, ouvrez l\'application installée › Paramètres › Notifications › Activer.');
        }

        return back()->with($reached ? 'success' : 'error', $reached
            ? 'Essai envoyé : '.$reached.' appareil(s) sur '.$devices->count().' atteint(s). S\'il n\'apparaît pas, vérifiez les réglages de notification du téléphone (mode Concentration, autorisations de l\'app).'
            : 'Aucun appareil atteint. Dernière erreur : '.($devices->first()->last_error ?? 'inconnue').'.');
    }
}
