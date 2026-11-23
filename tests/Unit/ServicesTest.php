<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SurveyService;
use Tests\TestCase;

final class ServicesTest extends TestCase
{
    public function testSurveyAudienceMatching(): void
    {
        $s = new SurveyService();
        $survey = static fn (array $x): array => $x + ['audience' => 'students', 'department_id' => null, 'course' => null, 'group_id' => null];
        $student = ['role' => 'student', 'department_id' => 1, 'course' => 2, 'group_id' => 7];
        $teacher = ['role' => 'teacher', 'department_id' => 1, 'course' => null, 'group_id' => null];
        $this->assertTrue($s->matches($survey([]), $student));
        $this->assertFalse($s->matches($survey([]), $teacher));
        $this->assertTrue($s->matches($survey(['audience' => 'all']), $teacher));
        $this->assertFalse($s->matches($survey(['department_id' => 2]), $student));
        $this->assertTrue($s->matches($survey(['course' => 2]), $student));
        $this->assertFalse($s->matches($survey(['course' => 3]), $student));
        $this->assertFalse($s->matches($survey(['audience' => 'all', 'group_id' => 7]), $teacher), 'A group survey is for students only');
        $this->assertTrue($s->matches($survey(['group_id' => 7]), $student));
    }
}
