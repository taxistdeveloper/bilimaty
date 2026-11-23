<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Speech → text on this server with Vosk (offline, Apache 2.0, https://alphacephei.com/vosk/).
 * The browser records the microphone as 16 kHz mono 16-bit PCM; bin/vosk-transcribe.py
 * runs the model and prints the text.
 */
final class RecognitionService
{
    public const RATE = 16000;

    public function enabled(string $locale): bool
    {
        $python = (string) config('assistant.vosk.python', '');

        return $python !== '' && is_executable($python) && is_dir($this->model($locale)) && is_file($this->script());
    }

    /** @param string $pcm raw 16-bit little-endian mono samples at self::RATE */
    public function transcribe(string $pcm, string $locale): string
    {
        $max = self::RATE * 2 * (int) config('assistant.vosk.max_seconds', 15);
        if ($pcm === '' || strlen($pcm) > $max || strlen($pcm) % 2 !== 0 || !$this->enabled($locale)) {
            throw new RuntimeException('Recognition is not available for this audio');
        }

        $command = [(string) config('assistant.vosk.python'), $this->script(), $this->model($locale), (string) self::RATE];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start vosk');
        }
        fwrite($pipes[0], $pcm);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + (int) config('assistant.vosk.timeout', 20);
        $text = '';
        $errors = '';
        while (proc_get_status($process)['running']) {
            $text .= (string) stream_get_contents($pipes[1]);
            $errors .= (string) stream_get_contents($pipes[2]);
            if (microtime(true) > $deadline) {
                proc_terminate($process);
                break;
            }
            usleep(20000);
        }
        $text .= (string) stream_get_contents($pipes[1]);
        $errors .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0 && trim($text) === '') {
            throw new RuntimeException('vosk failed: ' . mb_substr(trim($errors), -300));
        }

        return trim($text);
    }

    private function model(string $locale): string
    {
        return (string) (((array) config('assistant.vosk.models', []))[$locale] ?? '');
    }

    private function script(): string
    {
        return base_path('bin/vosk-transcribe.py');
    }
}
