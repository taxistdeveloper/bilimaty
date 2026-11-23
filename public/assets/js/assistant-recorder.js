/**
 * AudioWorklet for the assistant's offline voice input (Vosk): passes microphone
 * frames (Float32, context sample rate) to the page, which downsamples them to 16 kHz.
 */
class AssistantRecorder extends AudioWorkletProcessor {
    process(inputs) {
        const channel = inputs[0] && inputs[0][0];
        if (channel && channel.length) this.port.postMessage(channel.slice(0));
        return true;
    }
}

registerProcessor('assistant-recorder', AssistantRecorder);
