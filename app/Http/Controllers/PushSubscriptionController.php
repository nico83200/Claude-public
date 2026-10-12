<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Notifications\AppNotification;
use App\Services\WebPush;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Abonnement de l'appareil courant aux notifications. */
class PushSubscriptionController extends Controller
{
    public function store(Request $request, WebPush $push)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url'],
            'keys.p256dh' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-=]+$/'],
            'keys.auth' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-=]+$/'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);
        abort_unless(self::allowedEndpoint($data['endpoint']), 422, 'Service de notification non reconnu.');

        $hash = hash('sha256', $data['endpoint']);
        $sub = PushSubscription::firstOrNew(['endpoint_hash' => $hash]);
        // Un même appareil passe au compte connecté (changement d'utilisateur sur le téléphone).
        $sub->forceFill([
            'user_id' => $request->user()->id, 'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'device' => Str::limit((string) $request->userAgent(), 115, ''), 'failures' => 0,
        ])->save();

        return response()->json(['ok' => true, 'devices' => PushSubscription::where('user_id', $request->user()->id)->count()]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        PushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', hash('sha256', $request->input('endpoint')))->delete();

        return response()->json(['ok' => true]);
    }

    public function test(Request $request, WebPush $push)
    {
        $reached = $push->send($request->user(), (new AppNotification('push_test', 'Jack&Cie', 'Les notifications fonctionnent sur cet appareil.', route('dashboard')))->toWebPush($request->user()));

        return response()->json(['reached' => $reached, 'error' => $reached ? null : PushSubscription::where('user_id', $request->user()->id)->latest('last_failure_at')->value('last_error')], $reached ? 200 : 422);
    }

    /**
     * Accusé de réception envoyé par le service worker de l'appareil (sans session ni jeton :
     * l'adresse d'abonnement, propre à l'appareil, l'identifie). Sert uniquement au diagnostic.
     */
    public function receipt(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000'], 'error' => ['nullable', 'string', 'max:200']]);
        PushSubscription::where('endpoint_hash', hash('sha256', $data['endpoint']))->update([
            'last_received_at' => now(), 'last_display_error' => $data['error'] ?? null,
        ]);

        return response()->noContent();
    }

    public static function allowedEndpoint(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? '';
        $host = strtolower($parts['host'] ?? '');
        if ($host === '' || ! ($scheme === 'https' || ($scheme === 'http' && config('services.webpush.allow_http')))) {
            return false;
        }
        foreach (config('services.webpush.allowed_hosts', []) as $pattern) {
            $pattern = strtolower(trim($pattern));
            if ($host === $pattern || (str_starts_with($pattern, '*.') && str_ends_with($host, substr($pattern, 1)))) {
                return true;
            }
        }

        return false;
    }
}
