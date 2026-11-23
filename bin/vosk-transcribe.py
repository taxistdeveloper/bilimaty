#!/usr/bin/env python3
"""Offline speech recognition for the assistant (Vosk, Apache 2.0).

Usage: vosk-transcribe.py <model_dir> <sample_rate>  < raw 16-bit mono PCM on stdin
Prints the recognised text (lowercase, no punctuation) on stdout.
"""
import json
import sys

from vosk import KaldiRecognizer, Model, SetLogLevel


def main() -> None:
    SetLogLevel(-1)
    model = Model(sys.argv[1])
    recognizer = KaldiRecognizer(model, int(sys.argv[2]))
    data = sys.stdin.buffer.read()
    parts = []
    for i in range(0, len(data), 8000):
        if recognizer.AcceptWaveform(data[i:i + 8000]):
            parts.append(json.loads(recognizer.Result()).get("text", ""))
    parts.append(json.loads(recognizer.FinalResult()).get("text", ""))
    sys.stdout.write(" ".join(p for p in parts if p).strip())


if __name__ == "__main__":
    main()
