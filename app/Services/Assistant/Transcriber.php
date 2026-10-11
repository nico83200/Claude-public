<?php

namespace App\Services\Assistant;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Transcription de la voix par ElevenLabs (Speech-to-Text « Scribe »).
 * L'audio est transmis puis oublié : il n'est jamais écrit sur le disque de l'application.
 */
class Transcriber
{
    public function configured(): bool
    {
        return filled(config('services.elevenlabs.key'));
    }

    public function transcribe(UploadedFile $audio): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('Transcription non configurée (clé ElevenLabs manquante).');
        }
        $response = Http::withHeaders(['xi-api-key' => config('services.elevenlabs.key')])
            ->timeout(45)->retry(2, 500, throw: false)
            ->attach('file', $audio->get(), 'consigne.'.($audio->guessExtension() ?: 'webm'))
            ->post(rtrim(config('services.elevenlabs.base_url', 'https://api.elevenlabs.io'), '/').'/v1/speech-to-text', [
                'model_id' => config('services.elevenlabs.stt_model', 'scribe_v2'),
                'language_code' => 'fra',
                'tag_audio_events' => 'false',
            ]);
        if (! $response->successful()) {
            throw new RuntimeException('ElevenLabs a répondu '.$response->status().' : '.mb_substr((string) $response->body(), 0, 200));
        }

        return trim((string) $response->json('text', ''));
    }
}
