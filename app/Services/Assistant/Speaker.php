<?php

namespace App\Services\Assistant;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Réponse vocale de l'assistant (ElevenLabs Text-to-Speech) : la réponse écrite est lue à voix haute,
 * pratique les mains prises à l'écurie. Le son n'est jamais écrit sur le disque.
 */
class Speaker
{
    /** Voix par défaut (bibliothèque ElevenLabs), modifiable dans Administration › Assistant IA. */
    public const DEFAULT_VOICE = 'JBFqnCBsd6RMkjVDRZzb';

    public const MODELS = [
        'eleven_flash_v2_5' => 'Flash v2.5 — très rapide, économique (recommandé)',
        'eleven_multilingual_v2' => 'Multilingual v2 — plus naturel, plus lent',
    ];

    public function configured(): bool
    {
        return filled(config('services.elevenlabs.key')) && (bool) (ApplicationSetting::get('assistant.speech_enabled') ?? true);
    }

    public function voiceId(): string
    {
        $v = trim((string) ApplicationSetting::get('assistant.voice_id'));

        return preg_match('/^[A-Za-z0-9]{10,40}$/', $v) ? $v : self::DEFAULT_VOICE;
    }

    public function model(): string
    {
        $m = (string) ApplicationSetting::get('assistant.speech_model');

        return array_key_exists($m, self::MODELS) ? $m : 'eleven_flash_v2_5';
    }

    /** MP3 de la phrase lue (600 caractères au plus). */
    public function speak(string $text): string
    {
        if (! filled(config('services.elevenlabs.key'))) {
            throw new RuntimeException('Voix non configurée (clé ElevenLabs manquante).');
        }
        $text = trim(mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 600));
        if ($text === '') {
            throw new RuntimeException('Rien à lire.');
        }
        $response = Http::withHeaders(['xi-api-key' => config('services.elevenlabs.key'), 'Accept' => 'audio/mpeg'])
            ->timeout(30)->retry(1, 400, throw: false)
            ->post(rtrim(config('services.elevenlabs.base_url', 'https://api.elevenlabs.io'), '/').'/v1/text-to-speech/'.$this->voiceId().'?output_format=mp3_44100_64', [
                'text' => $text,
                'model_id' => $this->model(),
                'language_code' => 'fr',
            ]);
        if (! $response->successful()) {
            throw new RuntimeException(str_replace(['Transcription : ', '« Speech to Text »'], ['Voix : ', '« Text to Speech »'], Transcriber::explain($response->status(), (string) $response->body())));
        }

        return $response->body();
    }
}
