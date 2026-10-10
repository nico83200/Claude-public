<?php

namespace App\Http\Controllers;

use App\Models\FeatureFlag;
use App\Models\SyncConflict;
use App\Services\Sync\SyncRejected;
use App\Services\Sync\SyncService;
use Illuminate\Http\Request;

/**
 * Points d'entrée JSON de la synchronisation. Authentification par cookie de
 * session + jeton CSRF : aucun secret n'est stocké dans IndexedDB.
 */
class SyncController extends Controller
{
    public function __construct(private SyncService $sync) {}

    /** Rafraîchit le jeton CSRF après une période hors ligne. */
    public function session(Request $request)
    {
        return response()->json(['user_id' => $request->user()->id, 'csrf_token' => csrf_token()])->header('Cache-Control', 'no-store');
    }

    public function pull(Request $request)
    {
        abort_unless(FeatureFlag::enabled('offline'), 503, 'Mode hors ligne désactivé.');
        $data = $request->validate(['device_uuid' => ['required', 'uuid'], 'device_label' => ['nullable', 'string', 'max:100'], 'ack_wipe' => ['nullable', 'boolean']]);
        $device = $this->sync->device($request->user(), $data['device_uuid'], $data['device_label'] ?? null, $request->userAgent());

        return response()->json($this->sync->pull($request->user(), $device, (bool) ($data['ack_wipe'] ?? false)));
    }

    public function push(Request $request)
    {
        abort_unless(FeatureFlag::enabled('offline'), 503, 'Mode hors ligne désactivé.');
        $data = $request->validate([
            'device_uuid' => ['required', 'uuid'],
            'operations' => ['required', 'array', 'max:200'],
            'operations.*' => ['array'],
        ]);
        $device = $this->sync->device($request->user(), $data['device_uuid'], null, $request->userAgent());
        if ($device->wipe_requested_at) {
            return response()->json(['wipe' => true, 'results' => []]);
        }

        return response()->json(['wipe' => false, 'results' => $this->sync->push($request->user(), $device, $data['operations'])]);
    }

    public function conflicts(Request $request)
    {
        return response()->json(['conflicts' => SyncConflict::where('user_id', $request->user()->id)->where('status', 'open')->latest()->get()
            ->map(fn ($c) => $c->only(['id', 'entity', 'entity_uuid', 'local_values', 'server_values', 'created_at']))]);
    }

    public function resolve(Request $request, SyncConflict $conflict)
    {
        abort_unless($conflict->user_id === $request->user()->id, 404);
        $data = $request->validate(['choice' => ['required', 'in:local,server']]);
        try {
            $this->sync->resolve($request->user(), $conflict, $data['choice']);
        } catch (SyncRejected $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }
}
