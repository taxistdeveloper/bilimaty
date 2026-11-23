/**
 * Personal assistant: text or voice question → POST /api/assistant → answer shown and read aloud.
 * Voice uses the browser's Web Speech API (SpeechRecognition + speechSynthesis).
 */
(() => {
    'use strict';

    const root = document.querySelector('[data-assistant]');
    if (!root || !window.CollegeOS) return;

    const t = JSON.parse(document.getElementById('app-assistant-i18n')?.textContent || '{}');
    const panel = root.querySelector('.app-assistant__panel');
    const fab = root.querySelector('.app-assistant__fab');
    const log = root.querySelector('[data-assistant-log]');
    const form = root.querySelector('[data-assistant-form]');
    const input = form.querySelector('input');
    const mic = root.querySelector('[data-assistant-mic]');
    const speakBtn = root.querySelector('[data-assistant-speak]');
    const status = root.querySelector('[data-assistant-status]');
    const defaultStatus = status.textContent;

    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const synth = window.speechSynthesis;
    let speakOn = localStorage.getItem('assistant.speak') !== '0';
    let recognition = null;
    let listening = false;
    let busy = false;

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text; // never innerHTML with data
        return node;
    };

    const scroll = () => { log.scrollTop = log.scrollHeight; };

    const setStatus = (text) => { status.textContent = text || defaultStatus; };

    const renderSpeak = () => {
        speakBtn.setAttribute('aria-pressed', String(speakOn));
        speakBtn.querySelector('.bi').className = `bi ${speakOn ? 'bi-volume-up' : 'bi-volume-mute'}`;
    };

    /* ------------------------------------------------------------ voice output */

    const langPrefix = (t.lang || 'ru-RU').slice(0, 2);
    const voiceSelect = root.querySelector('[data-assistant-voice]');

    // Neural/online voices sound like a person; system "compact" voices sound robotic.
    const score = (v) => {
        const name = `${v.name} ${v.voiceURI}`.toLowerCase();
        let s = 0;
        if (v.lang?.toLowerCase().startsWith(langPrefix)) s += 1000;
        else if (v.lang?.toLowerCase().startsWith('ru')) s += 500; // Kazakh fallback: a Russian voice
        else return -1;
        if (/natural|neural|wavenet|online/.test(name)) s += 300;
        if (/google/.test(name)) s += 200;
        if (/premium|enhanced|улучш|siri/.test(name)) s += 150;
        if (!v.localService) s += 50;
        if (/compact|espeak/.test(name)) s -= 200;
        return s;
    };

    const voices = () => (synth ? synth.getVoices().filter((v) => score(v) >= 0).sort((a, b) => score(b) - score(a)) : []);

    const SERVER = 'server';
    // Neural voice from the server (Piper) unless the user picked a browser voice.
    const useServer = () => t.serverVoice && (localStorage.getItem('assistant.voice') || SERVER) === SERVER;

    const pickVoice = () => {
        const list = voices();
        const saved = localStorage.getItem('assistant.voice');
        return list.find((v) => v.voiceURI === saved) || list[0] || null;
    };

    const fillVoices = () => {
        if (!voiceSelect) return;
        const list = voices();
        const current = useServer() ? null : pickVoice();
        const options = list.map((v) => {
            const o = el('option', '', `${/natural|neural|online|google|premium|enhanced/i.test(v.name) ? '★ ' : ''}${v.name}`);
            o.value = v.voiceURI;
            o.selected = current?.voiceURI === v.voiceURI;
            return o;
        });
        if (t.serverVoice) {
            const o = el('option', '', `★ ${t.serverVoiceName}`);
            o.value = SERVER;
            o.selected = useServer();
            options.unshift(o);
        }
        voiceSelect.replaceChildren(...options);
        voiceSelect.disabled = options.length === 0;
    };

    const audio = new Audio();
    let sayToken = 0;

    const stopSpeaking = () => {
        sayToken++;
        audio.pause();
        synth?.cancel();
    };

    const say = async (text) => {
        if (!speakOn || !text) return;
        stopSpeaking();
        const token = sayToken;
        if (useServer()) {
            try {
                const { data } = await window.CollegeOS.api('/api/assistant/speech', { method: 'POST', body: { text } });
                if (token !== sayToken) return; // a newer answer or "stop" came meanwhile
                audio.src = data.url;
                await audio.play();
                return;
            } catch {
                if (token !== sayToken) return; // fall back to the browser voice
            }
        }
        sayBrowser(text);
    };

    const sayBrowser = (text) => {
        if (!synth) return;
        const voice = pickVoice();
        // Sentence by sentence: natural pauses, and Chrome stops long utterances after ~15 s.
        const parts = text.match(/[^.!?]+[.!?]*/g) || [text];
        parts.map((p) => p.trim()).filter(Boolean).forEach((part) => {
            const u = new SpeechSynthesisUtterance(part);
            u.lang = voice?.lang || t.lang || 'ru-RU';
            if (voice) u.voice = voice;
            u.rate = 1;
            u.pitch = 1;
            synth.speak(u);
        });
    };

    const userBubble = (text) => {
        log.appendChild(el('div', 'app-assistant__msg app-assistant__msg--user', text));
        scroll();
    };

    const botBubble = (answer) => {
        const box = el('div', 'app-assistant__msg app-assistant__msg--bot');
        box.appendChild(el('p', 'mb-0', answer.text));
        (answer.sections || []).forEach((section) => {
            const card = el('div', 'app-assistant__card');
            card.appendChild(el('div', 'app-assistant__card-title', section.title));
            (section.rows || []).forEach((row) => {
                const line = el('div', `app-assistant__row${row.off ? ' is-off' : ''}`);
                line.appendChild(el('div', 'app-assistant__time', row.time));
                const body = el('div', 'min-w-0');
                body.appendChild(el('div', 'app-assistant__subject', row.title));
                body.appendChild(el('div', 'app-assistant__meta', row.meta));
                line.appendChild(body);
                card.appendChild(line);
            });
            box.appendChild(card);
        });
        if (answer.link?.url) {
            const a = el('a', 'app-assistant__link', answer.link.label);
            a.href = answer.link.url;
            box.appendChild(a);
        }
        log.appendChild(box);
        scroll();
    };

    /** First opening: the mascot says hello. */
    const welcome = () => {
        const box = el('div', 'app-assistant__welcome');
        if (t.mascot) {
            const img = el('img', 'app-assistant__welcome-img');
            img.src = t.mascot;
            img.alt = '';
            box.appendChild(img);
        }
        box.appendChild(el('div', 'app-assistant__msg app-assistant__msg--bot', t.greeting));
        log.appendChild(box);
    };

    const ask = async (text) => {
        text = (text || '').trim();
        if (!text || busy) return;
        busy = true;
        userBubble(text);
        input.value = '';
        const typing = el('div', 'app-assistant__msg app-assistant__msg--bot app-assistant__typing', t.thinking);
        log.appendChild(typing);
        scroll();
        try {
            const { data } = await window.CollegeOS.api('/api/assistant', { method: 'POST', body: { text } });
            typing.remove();
            botBubble(data);
            say(data.speech || data.text);
        } catch {
            typing.remove();
            botBubble({ text: t.error });
        } finally {
            busy = false;
        }
    };

    const open = (show) => {
        panel.hidden = !show;
        root.querySelectorAll('[data-assistant-toggle]').forEach((b) => b.setAttribute('aria-expanded', String(show)));
        fab.classList.toggle('is-open', show);
        if (show) {
            if (!log.childElementCount) welcome();
            input.focus();
        } else {
            if (recorder) recorder.finish(false);
            stopListening();
            stopSpeaking();
        }
    };

    /* ------------------------------------------------------------ voice input */

    /* Offline recognition on the server (Vosk): record → 16 kHz PCM → /api/assistant/listen */
    let recorder = null;

    const stopListening = () => {
        if (recorder) recorder.finish();
        else if (recognition && listening) recognition.stop();
    };

    const toPcm16k = (chunks, rate) => {
        const length = chunks.reduce((n, c) => n + c.length, 0);
        const all = new Float32Array(length);
        let o = 0;
        chunks.forEach((c) => { all.set(c, o); o += c.length; });
        const ratio = rate / 16000;
        const out = new Int16Array(Math.floor(length / ratio));
        for (let i = 0; i < out.length; i++) {
            const from = Math.floor(i * ratio);
            const to = Math.min(length, Math.floor((i + 1) * ratio));
            let sum = 0;
            for (let j = from; j < to; j++) sum += all[j];
            const v = Math.max(-1, Math.min(1, sum / Math.max(1, to - from)));
            out[i] = v < 0 ? v * 0x8000 : v * 0x7fff;
        }
        let binary = '';
        const bytes = new Uint8Array(out.buffer);
        for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        return btoa(binary);
    };

    const listenServer = async () => {
        let stream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
        } catch {
            botBubble({ text: t.micDenied });
            return;
        }
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        await ctx.audioWorklet.addModule(t.recorderUrl);
        const source = ctx.createMediaStreamSource(stream);
        const node = new AudioWorkletNode(ctx, 'assistant-recorder');
        const chunks = [];
        const started = performance.now();
        let heard = 0;          // when speech was last heard (ms)
        let spoke = false;
        let done = false;

        const finish = async (send = true) => {
            if (done) return;
            done = true;
            recorder = null;
            node.port.onmessage = null;
            source.disconnect();
            node.disconnect();
            stream.getTracks().forEach((tr) => tr.stop());
            ctx.close();
            listening = false;
            mic.classList.remove('is-listening');
            if (!send || !spoke) {
                setStatus();
                if (send) botBubble({ text: t.notHeard });
                return;
            }
            setStatus(t.recognizing);
            try {
                const { data } = await window.CollegeOS.api('/api/assistant/listen', { method: 'POST', body: { audio: toPcm16k(chunks, ctx.sampleRate) } });
                setStatus();
                if (data.text) {
                    input.value = data.text;
                    ask(data.text);
                } else {
                    botBubble({ text: t.notHeard });
                }
            } catch {
                setStatus();
                botBubble({ text: t.error });
            }
        };

        node.port.onmessage = ({ data }) => {
            chunks.push(data);
            let sum = 0;
            for (let i = 0; i < data.length; i++) sum += data[i] * data[i];
            const now = performance.now();
            if (Math.sqrt(sum / data.length) > 0.02) { spoke = true; heard = now; }
            // stop after 1.2 s of silence following speech, 6 s of nothing, or 15 s in all
            if ((spoke && now - heard > 1200) || (!spoke && now - started > 6000) || now - started > 15000) finish();
        };
        source.connect(node);
        recorder = { finish };
        listening = true;
        mic.classList.add('is-listening');
        setStatus(t.listening);
    };

    const startListening = () => {
        if (t.serverEars && window.AudioWorkletNode && navigator.mediaDevices?.getUserMedia) {
            stopSpeaking();
            listenServer();
            return;
        }
        if (!Recognition) {
            botBubble({ text: t.noVoice });
            return;
        }
        stopSpeaking();
        recognition = new Recognition();
        recognition.lang = t.lang || 'ru-RU';
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;
        let finalText = '';

        recognition.onstart = () => {
            listening = true;
            mic.classList.add('is-listening');
            setStatus(t.listening);
        };
        recognition.onresult = (event) => {
            let interim = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const chunk = event.results[i][0].transcript;
                if (event.results[i].isFinal) finalText += chunk; else interim += chunk;
            }
            input.value = (finalText + interim).trim();
        };
        recognition.onerror = (event) => {
            if (event.error === 'not-allowed' || event.error === 'service-not-allowed') botBubble({ text: t.micDenied });
        };
        recognition.onend = () => {
            listening = false;
            mic.classList.remove('is-listening');
            setStatus();
            const text = (finalText || input.value).trim();
            if (text) ask(text);
        };
        recognition.start();
    };

    /* ------------------------------------------------------------ wiring */

    root.querySelectorAll('[data-assistant-toggle]').forEach((b) => b.addEventListener('click', () => open(panel.hidden)));
    root.querySelectorAll('[data-assistant-ask]').forEach((b) => b.addEventListener('click', () => ask(b.textContent)));
    form.addEventListener('submit', (e) => { e.preventDefault(); ask(input.value); });
    mic.addEventListener('click', () => (listening ? stopListening() : startListening()));
    speakBtn.addEventListener('click', () => {
        speakOn = !speakOn;
        localStorage.setItem('assistant.speak', speakOn ? '1' : '0');
        if (!speakOn) stopSpeaking();
        renderSpeak();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.hidden) open(false); });
    if (synth) {
        synth.getVoices(); // the list loads asynchronously in Chrome
        synth.addEventListener?.('voiceschanged', fillVoices);
        fillVoices();
    }
    root.querySelector('[data-assistant-voice-toggle]')?.addEventListener('click', (e) => {
        const box = root.querySelector('[data-assistant-voice-box]');
        box.hidden = !box.hidden;
        e.currentTarget.setAttribute('aria-expanded', String(!box.hidden));
        if (!box.hidden) fillVoices();
    });
    voiceSelect?.addEventListener('change', () => {
        localStorage.setItem('assistant.voice', voiceSelect.value);
        const was = speakOn;
        speakOn = true;
        say(t.voiceSample);
        speakOn = was;
    });
    renderSpeak();
})();
