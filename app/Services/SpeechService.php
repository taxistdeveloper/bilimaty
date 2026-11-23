<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Text → natural speech (WAV) with Piper, a free open-source neural TTS that runs on
 * this server (https://github.com/OHF-Voice/piper1-gpl). Results are cached by text,
 * so a repeated answer is served instantly.
 */
final class SpeechService
{
    public const MAX_LENGTH = 1500;
    private const CACHE_DAYS = 7;

    public function enabled(string $locale): bool
    {
        $bin = (string) config('assistant.piper.bin', '');

        return $bin !== '' && is_executable($bin) && is_file($this->voice($locale));
    }

    /** Audio file key (64 hex) for the text; synthesised on the first request. */
    public function synthesize(string $text, string $locale): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '' || mb_strlen($text) > self::MAX_LENGTH || !$this->enabled($locale)) {
            throw new RuntimeException('Speech is not available');
        }
        $voice = $this->voice($locale);
        $scale = (float) config('assistant.piper.length_scale', 1.08);
        $noise = (float) config('assistant.piper.noise_scale', 0.75);
        $noiseW = (float) config('assistant.piper.noise_w', 0.9);
        $silence = (float) config('assistant.piper.sentence_silence', 0.3);
        $speaker = (string) (config('assistant.piper.speakers', [])[$locale] ?? '');
        $key = hash('sha256', $locale . '|' . $voice . '|' . $speaker . '|' . $scale . '|' . $noise . '|' . $noiseW . '|' . $silence . '|' . $text);
        $file = $this->path($key);
        if (is_file($file)) {
            return $key;
        }

        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create ' . $dir);
        }
        $this->cleanup($dir);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $command = [(string) config('assistant.piper.bin'), '--model', $voice, '--output_file', $tmp, '--length_scale', (string) $scale,
            '--noise_scale', (string) $noise, '--noise_w', (string) $noiseW, '--sentence_silence', (string) $silence];
        if (ctype_digit($speaker)) {
            array_push($command, '--speaker', $speaker);
        }
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start piper');
        }
        fwrite($pipes[0], $text . "\n");
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + (int) config('assistant.piper.timeout', 30);
        $errors = '';
        while (proc_get_status($process)['running']) {
            $errors .= (string) stream_get_contents($pipes[2]);
            stream_get_contents($pipes[1]);
            if (microtime(true) > $deadline) {
                proc_terminate($process);
                break;
            }
            usleep(20000);
        }
        $errors .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if (!is_file($tmp) || filesize($tmp) < 1000) {
            @unlink($tmp);
            throw new RuntimeException('piper failed: ' . mb_substr(trim($errors), -300));
        }
        rename($tmp, $file);

        return $key;
    }

    /** Cached audio of a key, or null. */
    public function file(string $key): ?string
    {
        if (preg_match('/^[a-f0-9]{64}$/', $key) !== 1) {
            return null;
        }
        $file = $this->path($key);

        return is_file($file) ? $file : null;
    }

    private function voice(string $locale): string
    {
        $voices = (array) config('assistant.piper.voices', []);

        return (string) (($voices[$locale] ?? '') !== '' ? $voices[$locale] : ($voices['ru'] ?? ''));
    }

    private function path(string $key): string
    {
        return storage_path('cache/tts/' . substr($key, 0, 2) . '/' . $key . '.wav');
    }

    /** Now and then drop audio older than a week. */
    private function cleanup(string $dir): void
    {
        if (random_int(1, 50) !== 1) {
            return;
        }
        foreach (glob(dirname($dir) . '/*/*.wav') ?: [] as $f) {
            if (filemtime($f) < time() - self::CACHE_DAYS * 86400) {
                @unlink($f);
            }
        }
    }
}
