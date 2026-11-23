<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AssistantService;
use DateTimeImmutable;
use Tests\TestCase;

/** How the assistant understands questions (Monday 2026-11-23, 10:00). */
final class AssistantTest extends TestCase
{
    private function parse(string $text): array
    {
        $repo = (new \ReflectionClass(\App\Repositories\ScheduleRepository::class))->newInstanceWithoutConstructor();

        return (new AssistantService($repo, new DateTimeImmutable('2026-11-23 10:00')))->parse($text);
    }

    public function testDays(): void
    {
        $this->assertSame(['intent' => 'day', 'date' => '2026-11-24'], $this->parse('Завтра как у меня пара?'));
        $this->assertSame(['intent' => 'day', 'date' => '2026-11-25'], $this->parse('что послезавтра'));
        $this->assertSame(['intent' => 'day', 'date' => '2026-11-23'], $this->parse('какие пары'));
        $this->assertSame(['intent' => 'day', 'date' => '2026-11-27'], $this->parse('расписание на пятницу'));
        $this->assertSame(['intent' => 'day', 'date' => '2026-11-23'], $this->parse('в понедельник'));
        $this->assertSame(['intent' => 'day', 'date' => '2026-11-24'], $this->parse('Ертең қандай сабақтар?'));
        $this->assertSame(['intent' => 'day', 'date' => '2026-11-29'], $this->parse('жексенбі'));
        $this->assertSame(['intent' => 'day', 'date' => '2026-11-28'], $this->parse('сенбіде сабақ бар ма'));
    }

    public function testOtherIntents(): void
    {
        $this->assertSame('next', $this->parse('Какая следующая пара?')['intent']);
        $this->assertSame('next', $this->parse('келесі сабақ')['intent']);
        $this->assertSame(['intent' => 'week', 'from' => '2026-11-23', 'to' => '2026-11-29'], $this->parse('расписание на неделю'));
        $this->assertSame('2026-11-30', $this->parse('пары на следующей неделе')['from']);
        $this->assertSame('exams', $this->parse('Когда экзамены?')['intent']);
        $this->assertSame('exams', $this->parse('емтихан қашан')['intent']);
        $this->assertSame('grades', $this->parse('мои оценки')['intent']);
        $this->assertSame('help', $this->parse('что ты умеешь')['intent']);
        $this->assertSame('hello', $this->parse('привет')['intent']);
        $this->assertSame('unknown', $this->parse('какая погода')['intent']);
    }
}
