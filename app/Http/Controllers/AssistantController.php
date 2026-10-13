<?php

namespace App\Http\Controllers;

use App\Models\AssistantAction;
use App\Models\AssistantRequest;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\AssistantUndo;
use App\Services\Assistant\Transcriber;
use App\Services\Upsell;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/** Assistant vocal : transcription, exécution des consignes, historique et annulation. */
class AssistantController extends Controller
{
    public function __construct(private AssistantService $assistant) {}

    public function status(Request $request, Transcriber $transcriber)
    {
        $org = $this->currentOrganization();
        $reason = $this->assistant->unavailableReason($org);
        $offer = null;
        // On ne vante l'assistant que s'il fonctionne réellement sur cette instance.
        if ($reason && $this->assistant->serviceReady()) {
            $upsells = app(Upsell::class);
            $offer = $upsells->for($org, $request->user(), 'assistant')
                ?? ($this->assistant->remaining($org) === 0 ? $upsells->for($org, $request->user(), 'assistant_monthly') : null);
        }

        return response()->json([
            'available' => ! $reason,
            'reason' => $reason,
            'upsell' => $offer ? ['plan' => $offer['plan']->name, 'title' => $offer['title'], 'text' => $offer['text'], 'note' => $offer['note'],
                'url' => $offer['can_upgrade'] ? $offer['url'] : null, 'cta' => $offer['cta'], 'trial' => $offer['trial_days'] > 0] : null,
            'voice' => $transcriber->configured(),
            'speech' => app(\App\Services\Assistant\Speaker::class)->configured(),
            'remaining' => $this->assistant->remaining($org),
        ]);
    }

    public function transcribe(Request $request, Transcriber $transcriber)
    {
        $org = $this->currentOrganization();
        if ($reason = $this->assistant->unavailableReason($org)) {
            return response()->json(['message' => $reason], 403);
        }
        $request->validate(['audio' => ['required', 'file', 'max:15360']]); // 15 Mo ≈ 2 min
        $mime = (string) $request->file('audio')->getMimeType();
        abort_unless(str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/') || $mime === 'application/octet-stream', 422, 'Format audio non reconnu.');
        try {
            $text = $transcriber->transcribe($request->file('audio'));
        } catch (Throwable $e) {
            report($e);
            $message = $transcriber->configured() ? 'La transcription a échoué. Réessayez ou écrivez la consigne.' : 'La dictée vocale n\'est pas configurée : écrivez la consigne.';
            // Le super-administrateur voit la cause exacte pour pouvoir la corriger.
            if ($request->user()->is_super_admin && $transcriber->configured()) {
                $message .= ' — '.mb_substr($e->getMessage(), 0, 400);
            }

            return response()->json(['message' => $message], 502);
        }

        return response()->json(['text' => $text]);
    }

    public function command(Request $request)
    {
        $org = $this->currentOrganization();
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'mode' => ['nullable', 'in:voice,text'],
            'conversation_id' => ['nullable', 'uuid'],
            'horse_id' => ['nullable', 'integer'],
        ]);
        try {
            $result = $this->assistant->handle($request->user(), $org, $data['text'], $data['mode'] ?? 'text', $data['conversation_id'] ?? null, $data['horse_id'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
        $req = $result['request'];

        return response()->json([
            'request_id' => $req->uuid,
            'conversation_id' => $req->conversation_id,
            'status' => $req->status,
            'reply' => $req->reply,
            'question' => $result['question'],
            'actions' => array_map(fn (AssistantAction $a) => $this->actionPayload($a), $result['actions']),
            'remaining' => $this->assistant->remaining($org),
        ]);
    }

    /** Réponse d'une consigne lue à voix haute (ElevenLabs), mise en cache 30 minutes. */
    public function speak(Request $request, string $uuid, \App\Services\Assistant\Speaker $speaker)
    {
        abort_unless($speaker->configured(), 404);
        $req = \App\Models\AssistantRequest::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();
        abort_if(trim((string) $req->reply) === '', 404);
        try {
            $audio = \Illuminate\Support\Facades\Cache::remember('assistant:speech:'.$req->uuid, now()->addMinutes(30), fn () => base64_encode($speaker->speak($req->reply)));
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['message' => $request->user()->is_super_admin ? $e->getMessage() : 'Lecture vocale indisponible.'], 503);
        }

        return response(base64_decode($audio), 200, ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'private, max-age=1800']);
    }

    public function undo(Request $request, AssistantAction $action, AssistantUndo $undo)
    {
        try {
            $undo->undo($action, $request->user());
        } catch (RuntimeException $e) {
            return $request->expectsJson() ? response()->json(['message' => $e->getMessage()], 422) : back()->with('error', $e->getMessage());
        }

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Action annulée : '.$action->summary);
    }

    public function history(Request $request)
    {
        $org = $this->currentOrganization();

        return view('assistant.history', [
            'requests' => AssistantRequest::with('actions')->where('user_id', $request->user()->id)->latest()->paginate(25),
            'unavailable' => $this->assistant->unavailableReason($org),
            'remaining' => $this->assistant->remaining($org),
            'used' => $this->assistant->usedThisMonth($org),
        ]);
    }

    private function actionPayload(AssistantAction $a): array
    {
        return ['id' => $a->id, 'type' => $a->type, 'summary' => $a->summary, 'url' => $a->url, 'undoable' => $a->isUndoable(), 'undo_url' => route('assistant.undo', $a)];
    }
}
