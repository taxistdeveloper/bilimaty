<?php
/** Floating personal assistant (voice + text). Logic: public/assets/js/assistant.js, answers: POST /api/assistant. */
$strings = [
    'listening' => __('assistant.listening'),
    'thinking' => __('assistant.thinking'),
    'noVoice' => __('assistant.no_voice'),
    'micDenied' => __('assistant.mic_denied'),
    'error' => __('assistant.error'),
    'greeting' => __('assistant.greeting'),
    'voiceSample' => __('assistant.voice_sample'),
    'serverVoice' => (new App\Services\SpeechService())->enabled(locale()),
    'serverVoiceName' => __('assistant.voice_server'),
    'serverEars' => (new App\Services\RecognitionService())->enabled(locale()),
    'recorderUrl' => asset('assets/js/assistant-recorder.js'),
    'recognizing' => __('assistant.recognizing'),
    'notHeard' => __('assistant.not_heard'),
    'lang' => locale() === 'kk' ? 'kk-KZ' : 'ru-RU',
    'mascot' => asset('assets/images/mascot-assistant.png'),
];
?>
<div class="app-assistant" data-assistant>
    <button type="button" class="app-assistant__fab" data-assistant-toggle aria-expanded="false"
            aria-controls="appAssistantPanel" aria-label="<?= e(__('assistant.open')) ?>" title="<?= e(__('assistant.title')) ?>">
        <span class="app-assistant__head-crop"><img src="<?= e(asset('assets/images/mascot-assistant.png')) ?>" alt="" width="322" height="480"></span>
        <span class="app-assistant__fab-label"><?= e(__('assistant.title')) ?></span>
    </button>

    <section class="app-assistant__panel shadow" id="appAssistantPanel" role="dialog" aria-label="<?= e(__('assistant.title')) ?>" hidden>
        <header class="app-assistant__head">
            <span class="app-assistant__avatar app-assistant__head-crop" aria-hidden="true"><img src="<?= e(asset('assets/images/mascot-assistant.png')) ?>" alt="" width="322" height="480"></span>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold"><?= e(__('assistant.title')) ?></div>
                <div class="small opacity-75 text-truncate" data-assistant-status><?= e(__('assistant.subtitle')) ?></div>
            </div>
            <button type="button" class="btn btn-sm app-assistant__icon-btn" data-assistant-voice-toggle aria-expanded="false"
                    aria-controls="appAssistantVoice" title="<?= e(__('assistant.voice')) ?>" aria-label="<?= e(__('assistant.voice')) ?>">
                <i class="bi bi-soundwave" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm app-assistant__icon-btn" data-assistant-speak aria-pressed="true" title="<?= e(__('assistant.speak_on')) ?>" aria-label="<?= e(__('assistant.speak_on')) ?>">
                <i class="bi bi-volume-up" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm app-assistant__icon-btn" data-assistant-toggle aria-label="<?= e(__('assistant.close')) ?>">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </header>

        <div class="app-assistant__voice" id="appAssistantVoice" data-assistant-voice-box hidden>
            <label class="form-label small fw-semibold mb-1" for="appAssistantVoiceSelect"><?= e(__('assistant.voice')) ?></label>
            <select class="form-select form-select-sm" id="appAssistantVoiceSelect" data-assistant-voice></select>
            <div class="form-text"><?= e(__('assistant.voice_tip')) ?></div>
        </div>

        <div class="app-assistant__log" data-assistant-log aria-live="polite"></div>

        <div class="app-assistant__chips" data-assistant-chips>
            <?php foreach (explode('|', __('assistant.suggestions')) as $s): ?>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-assistant-ask><?= e($s) ?></button>
            <?php endforeach; ?>
        </div>

        <form class="app-assistant__form" data-assistant-form>
            <button type="button" class="app-assistant__mic" data-assistant-mic aria-label="<?= e(__('assistant.mic')) ?>" title="<?= e(__('assistant.mic')) ?>">
                <i class="bi bi-mic-fill" aria-hidden="true"></i>
            </button>
            <input type="text" class="form-control" name="text" maxlength="300" autocomplete="off"
                   placeholder="<?= e(__('assistant.placeholder')) ?>" aria-label="<?= e(__('assistant.placeholder')) ?>">
            <button type="submit" class="btn btn-primary" aria-label="<?= e(__('assistant.send')) ?>">
                <i class="bi bi-send" aria-hidden="true"></i>
            </button>
        </form>
    </section>
    <script type="application/json" id="app-assistant-i18n"><?= json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</div>
