<?php

declare(strict_types=1);

/**
 * Assistant voice. With Piper (free, open-source neural TTS running on this server,
 * no internet needed) everyone hears the same natural voice; without it the browser's
 * own voice is used. Install: see README, section "Голос ассистента".
 */
return [
    'piper' => [
        // Path to the piper executable ("" = voice from the browser)
        'bin' => env('PIPER_BIN', ''),
        // Voice models (.onnx with the .onnx.json next to it) per interface language
        'voices' => [
            'ru' => env('PIPER_VOICE_RU', ''),
            'kk' => env('PIPER_VOICE_KK', ''),
        ],
        // Speaker number for multi-speaker models ("" = the model's default)
        'speakers' => [
            'ru' => env('PIPER_SPEAKER_RU', ''),
            'kk' => env('PIPER_SPEAKER_KK', ''),
        ],
        // Delivery: >1 slower and calmer, <1 faster
        'length_scale' => (float) env('PIPER_LENGTH_SCALE', 1.08),
        // Intonation liveliness (model default 0.667): higher = more expressive, less monotone
        'noise_scale' => (float) env('PIPER_NOISE_SCALE', 0.75),
        // Rhythm variation between sounds (model default 0.8): higher = less "robotic" evenness
        'noise_w' => (float) env('PIPER_NOISE_W', 0.9),
        // Pause after each sentence, seconds: a breath between sentences
        'sentence_silence' => (float) env('PIPER_SENTENCE_SILENCE', 0.3),
        'timeout' => 30,
    ],

    // Offline speech recognition (what the user says → text) with Vosk, instead of the
    // browser's (which in Chrome sends audio to Google and does not exist in Firefox).
    // Enable: php cli.php assistant:vosk  (downloads Vosk and the model, prints the .env lines)
    'vosk' => [
        // Python with the "vosk" package ("" = the browser's own recognition)
        'python' => env('VOSK_PYTHON', ''),
        // Unpacked model folders per interface language
        'models' => [
            'ru' => env('VOSK_MODEL_RU', ''),
            'kk' => env('VOSK_MODEL_KK', ''),
        ],
        // What `assistant:vosk` downloads (Apache 2.0, ~45 MB)
        'download' => 'https://alphacephei.com/vosk/models/vosk-model-small-ru-0.22.zip',
        'timeout' => 20,
        'max_seconds' => 15,
    ],
];
