<?php

namespace App\Http\Controllers;

use App\Models\AssistantAction;
use App\Models\AssistantRequest;
use App\Services\Assistant\AssistantCredits;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\AssistantUndo;
use App\Services\Assistant\Speaker;
use App\Services\Assistant\Transcriber;
use App\Services\Assistant\VoiceCreditsExhausted;
use App\Services\SystemMonitor;
use App\Services\Upsell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $offer = $exhausted = null;
        // On ne vante l'assistant que s'il fonctionne réellement sur cette instance.
        if ($reason && $this->assistant->serviceReady()) {
            $upsells = app(Upsell::class);
            $offer = $upsells->for($org, $request->user(), 'assistant');
            // Solde IA épuisé : explication, achat de crédits et, à défaut, offre supérieure.
            if (! $offer && $this->assistant->remaining($org) === 0) {
                $credits = app(AssistantCredits::class);
                $packs = $credits->purchasable() ? AssistantCredits::packs() : [];
                $more = $upsells->for($org, $request->user(), 'assistant_monthly');
                $exhausted = [
                    'title' => 'Solde IA épuisé',
                    'text' => 'Les '.$this->entitlements()->limit($org, 'assistant_monthly').' consignes IA comprises dans votre offre ont été utilisées ce mois-ci. Le quota se renouvelle le 1er '.now()->addMonthNoOverflow()->startOfMonth()->translatedFormat('F').'.',
                    'can_buy' => $credits->canBuy($org, $request->user()),
                    'buy_url' => route('billing.index').'#credits-ia',
                    'from' => $packs ? 'À partir de '.AssistantCredits::price($packs[0]['price_cents']).' les '.$packs[0]['credits'].' crédits, sans abonnement supplémentaire.' : null,
                    'note' => $packs && ! $credits->canBuy($org, $request->user()) ? 'Demandez à la personne qui gère l\'abonnement de l\'espace '.$org->name.' d\'acheter des crédits IA.' : null,
                    'upgrade' => $more && $more['can_upgrade'] ? ['url' => $more['url'], 'label' => 'Ou passer à l\'offre '.$more['plan']->name] : null,
                ];
            }
        }

        return response()->json([
            'available' => ! $reason,
            'reason' => $reason,
            'exhausted' => $exhausted,
            'upsell' => $offer ? ['plan' => $offer['plan']->name, 'title' => $offer['title'], 'text' => $offer['text'], 'note' => $offer['note'],
                'url' => $offer['can_upgrade'] ? $offer['url'] : null, 'cta' => $offer['cta'], 'trial' => $offer['trial_days'] > 0] : null,
            'voice' => $transcriber->configured(),
            'speech' => app(Speaker::class)->configured(),
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
        } catch (VoiceCreditsExhausted $e) {
            report($e);
            SystemMonitor::voiceCreditsExhausted($e->getMessage());

            return response()->json(['message' => 'La dictée vocale est momentanément indisponible, elle revient bientôt. Écrivez votre consigne en attendant.', 'code' => 'voice_credits'], 503);
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

    /**
     * Réponse d'une consigne lue à voix haute (ElevenLabs), morceau par morceau : la première phrase,
     * courte, arrive vite et se joue pendant que la suite est générée. Chaque morceau est mis en cache 30 minutes.
     */
    public function speak(Request $request, string $uuid, Speaker $speaker)
    {
        abort_unless($speaker->configured(), 404);
        $req = AssistantRequest::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();
        $parts = $speaker->parts((string) $req->reply);
        $part = (int) $request->query('partie', 0);
        abort_if(! isset($parts[$part]), 404);
        $headers = ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'private, max-age=1800', 'X-Speech-Parts' => (string) count($parts)];
        $key = 'assistant:speech:'.$req->uuid.':'.$part;
        // Erreur récente : réponse immédiate, sans nouvel appel à ElevenLabs.
        if ($error = Cache::get('assistant:speech-error:'.$req->uuid)) {
            return response()->json($error, 503);
        }
        try {
            $audio = Cache::remember($key, now()->addMinutes(30), fn () => base64_encode($speaker->speak($parts[$part], $parts[$part - 1] ?? '', $parts[$part + 1] ?? '')));
        } catch (VoiceCreditsExhausted $e) {
            report($e);
            SystemMonitor::voiceCreditsExhausted($e->getMessage());
            $error = ['message' => 'La lecture vocale est momentanément indisponible, elle revient bientôt.', 'code' => 'voice_credits'];
            Cache::put('assistant:speech-error:'.$req->uuid, $error, now()->addMinutes(5));

            return response()->json($error, 503);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['message' => $request->user()->is_super_admin ? $e->getMessage() : 'Lecture vocale indisponible pour le moment.'], 503);
        }

        return response(base64_decode($audio), 200, $headers);
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
