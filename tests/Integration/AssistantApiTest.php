<?php

declare(strict_types=1);

namespace Tests\Integration;

final class AssistantApiTest extends IntegrationTestCase
{
    public function testStudentAsksAboutLessonsAndHearsTheAnswer(): void
    {
        $student = $this->as('qasymova.i');
        $r = $student->json('POST', '/api/assistant', ['text' => 'Какие пары завтра?']);
        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['data']['data']['text'] !== '' && $r['data']['data']['speech'] !== '');

        $speech = $student->json('POST', '/api/assistant/speech', ['text' => 'Завтра у вас две пары.']);
        if ($speech['status'] === 503) {
            return; // Piper is not configured on this machine: the browser voice is used
        }
        $this->assertSame(200, $speech['status']);
        $this->assertMatchesRegularExpression('#/api/assistant/speech/[a-f0-9]{64}$#', (string) $speech['data']['data']['url']);
    }

    public function testUnknownQuestionAndForeignAudio(): void
    {
        $r = $this->as('qasymova.i')->json('POST', '/api/assistant', ['text' => 'какая погода']);
        $this->assertSame(200, $r['status']);
        $this->assertSame(404, $this->as('qasymova.i')->json('GET', '/api/assistant/speech/' . str_repeat('0', 64))['status']);
    }

    public function testOfflineVoiceInput(): void
    {
        $student = $this->as('qasymova.i');
        $silence = $student->json('POST', '/api/assistant/listen', ['audio' => base64_encode(str_repeat("\0", 16000 * 2))]);
        if ($silence['status'] === 503) {
            return; // Vosk is not configured on this machine: the browser recognises speech
        }
        $this->assertSame(200, $silence['status']);
        $this->assertSame('', $silence['data']['data']['text'], 'One second of silence');
        $this->assertSame(422, $student->json('POST', '/api/assistant/listen', ['audio' => '%%%'])['status'], 'Not base64');
        $tooLong = $student->json('POST', '/api/assistant/listen', ['audio' => base64_encode(str_repeat("\0", 16000 * 2 * 16))]);
        $this->assertSame(503, $tooLong['status'], 'Longer than 15 s');
    }
}
