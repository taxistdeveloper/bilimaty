<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AssistantService;
use App\Services\RecognitionService;
use App\Services\SpeechService;

/**
 * Personal assistant.
 *   POST /api/assistant {text}             → {text, speech, sections, link}
 *   POST /api/assistant/speech {text}      → {url} of a natural-voice WAV (Piper), 503 when not installed
 *   GET  /api/assistant/speech/{key}       → the WAV
 *   POST /api/assistant/listen {audio}     → {text}: offline recognition (Vosk) of base64 16 kHz mono PCM, 503 when not installed
 */
final class AssistantController extends Controller
{
    public function ask(Request $request): Response
    {
        $text = trim((string) $request->input('text', ''));
        if ($text === '' || mb_strlen($text) > 300) {
            return $this->json(['text' => __('assistant.not_understood'), 'speech' => __('assistant.not_understood'), 'sections' => [], 'link' => null]);
        }

        return $this->json((new AssistantService())->answer($text, (int) Auth::id()));
    }

    public function speech(Request $request): Response
    {
        $speech = new SpeechService();
        if (!$speech->enabled(locale())) {
            return $this->json(null, 'Speech is not configured', 503);
        }
        try {
            $key = $speech->synthesize((string) $request->input('text', ''), locale());
        } catch (\RuntimeException $e) {
            error_log('[assistant] ' . $e->getMessage());

            return $this->json(null, 'Speech failed', 503);
        }

        return $this->json(['url' => url('/api/assistant/speech/' . $key)]);
    }

    public function audio(string $key): Response
    {
        $file = (new SpeechService())->file($key) ?? throw new HttpException(404);

        return new Response((string) file_get_contents($file), 200, [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }

    public function listen(Request $request): Response
    {
        $recognition = new RecognitionService();
        if (!$recognition->enabled(locale())) {
            return $this->json(null, 'Recognition is not configured', 503);
        }
        $pcm = base64_decode((string) $request->input('audio', ''), true);
        if ($pcm === false || $pcm === '') {
            return $this->json(null, 'No audio', 422);
        }
        try {
            $text = $recognition->transcribe($pcm, locale());
        } catch (\RuntimeException $e) {
            error_log('[assistant] ' . $e->getMessage());

            return $this->json(null, 'Recognition failed', 503);
        }

        return $this->json(['text' => $text]);
    }
}
