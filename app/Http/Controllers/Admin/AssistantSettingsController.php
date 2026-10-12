<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationSetting;
use App\Models\AssistantRequest;
use App\Services\Assistant\AssistantConfig;
use App\Services\Assistant\ClaudeGateway;
use App\Services\Audit;
use App\Services\IntegrationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Throwable;

/** Réglages de l'assistant vocal : clés, modèle, consignes libres pour l'IA, suivi de l'usage. */
class AssistantSettingsController extends Controller
{
    public function edit(AssistantConfig $config, IntegrationSettings $settings)
    {
        $month = AssistantRequest::where('created_at', '>=', now()->startOfMonth());

        return view('admin.assistant', [
            'config' => $config,
            'masked' => ['anthropic.key' => $settings->masked('anthropic.key'), 'elevenlabs.key' => $settings->masked('elevenlabs.key')],
            'configured' => ['anthropic' => filled(config('services.anthropic.key')), 'elevenlabs' => filled(config('services.elevenlabs.key'))],
            'stats' => [
                'requests' => (clone $month)->count(),
                'errors' => (clone $month)->where('status', 'error')->count(),
                'voice' => (clone $month)->where('input_mode', 'voice')->count(),
                'input_tokens' => (int) (clone $month)->sum('input_tokens'),
                'output_tokens' => (int) (clone $month)->sum('output_tokens'),
                'avg_ms' => (int) (clone $month)->where('status', '!=', 'error')->avg('duration_ms'),
                'undone' => DB::table('assistant_actions')->whereNotNull('undone_at')->where('created_at', '>=', now()->startOfMonth())->count(),
                'actions' => DB::table('assistant_actions')->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'recentErrors' => AssistantRequest::with('user')->where('status', 'error')->latest()->limit(8)->get(),
        ]);
    }

    public function update(Request $request, IntegrationSettings $settings)
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'model' => ['required', Rule::in(array_keys(AssistantConfig::MODELS))],
            'effort' => ['required', Rule::in(array_keys(AssistantConfig::EFFORTS))],
            'session_min' => ['required', 'integer', 'min:10', 'max:240'],
            'session_max' => ['required', 'integer', 'min:10', 'max:240', 'gte:session_min'],
            'result_seconds' => ['sometimes', 'integer', Rule::in(array_keys(AssistantConfig::RESULT_DELAYS))],
            'instructions' => ['nullable', 'string', 'max:8000'],
            'anthropic_key' => ['nullable', 'string', 'max:255', 'regex:/^sk-ant-/'],
            'elevenlabs_key' => ['nullable', 'string', 'max:255'],
            'clear' => ['array'], 'clear.*' => [Rule::in(['anthropic.key', 'elevenlabs.key'])],
        ], ['anthropic_key.regex' => 'La clé Anthropic commence par sk-ant-.', 'session_max.gte' => 'La durée maximale doit être supérieure ou égale à la durée minimale.']);

        ApplicationSetting::put('assistant.enabled', $request->boolean('enabled'));
        foreach (array_intersect_key($data, array_flip(['model', 'effort', 'session_min', 'session_max', 'result_seconds'])) as $k => $v) {
            ApplicationSetting::put('assistant.'.$k, $v);
        }
        ApplicationSetting::put('assistant.instructions', $data['instructions'] ?? null);
        $settings->save(['anthropic.key' => $data['anthropic_key'] ?? null, 'elevenlabs.key' => $data['elevenlabs_key'] ?? null], $data['clear'] ?? []);
        Audit::log('admin.assistant_settings_updated', null, ['model' => $data['model'], 'effort' => $data['effort'], 'keys_changed' => array_keys(array_filter(['anthropic' => $data['anthropic_key'] ?? null, 'elevenlabs' => $data['elevenlabs_key'] ?? null]))]);

        return back()->with('success', 'Réglages de l\'assistant enregistrés.');
    }

    public function test(Request $request, ClaudeGateway $claude, \App\Services\Assistant\Transcriber $transcriber)
    {
        $which = $request->validate(['service' => ['required', Rule::in(['anthropic', 'elevenlabs'])]])['service'];
        try {
            if ($which === 'anthropic') {
                $r = $claude->send([['type' => 'text', 'text' => 'Réponds uniquement « OK ».']], [['role' => 'user', 'content' => 'Test de connexion']], []);

                return back()->with('success', 'Claude répond ('.$r['model'].') : '.trim(implode(' ', $r['texts'])));
            }
            abort_unless(filled(config('services.elevenlabs.key')), back()->with('error', 'Aucune clé ElevenLabs configurée.'));
            // Vrai essai de transcription (1 seconde de son) : vérifie la clé, sa permission « Speech to Text » et les crédits.
            $transcriber->transcribeBytes(\App\Services\Assistant\Transcriber::sampleWav(), 'essai.wav');

            return back()->with('success', 'Transcription ElevenLabs opérationnelle (modèle '.config('services.elevenlabs.stt_model', 'scribe_v2').').');
        } catch (Throwable $e) {
            return back()->with('error', 'Échec du test : '.mb_substr($e->getMessage(), 0, 300));
        }
    }
}
