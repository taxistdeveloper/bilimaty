<?php

declare(strict_types=1);

namespace Tests\Integration;

/**
 * Reports of performance and attendance, the teacher's pages and the student's grades address.
 */
final class ReportsTest extends IntegrationTestCase
{
    private function group(string $code): int
    {
        return (int) $this->value('SELECT id FROM `groups` WHERE code = ?', [$code]);
    }

    private function semester(): int
    {
        return (int) $this->value('SELECT id FROM semesters WHERE CURDATE() BETWEEN start_date AND end_date LIMIT 1');
    }

    public function testAttendanceMatchesTheJournal(): void
    {
        $group = $this->group('ПО-26-1');
        $semester = $this->semester();
        $student = $this->studentId('qasymova.i');
        $absent = (int) $this->value(
            "SELECT COUNT(*) FROM attendance at JOIN journal_lessons jl ON jl.id = at.journal_lesson_id JOIN teacher_subject_assignments a ON a.id = jl.assignment_id
             WHERE a.group_id = ? AND a.semester_id = ? AND at.student_id = ? AND at.status = 'absent'",
            [$group, $semester, $student]
        );
        $csv = $this->as('admin')->get("/reports/attendance?group={$group}&semester={$semester}&format=csv");
        $this->assertStringContainsString('text/csv', $csv['type']);
        $line = array_values(array_filter(explode("\n", $csv['body']), static fn (string $l): bool => str_contains($l, 'Қасымова І')))[0] ?? '';
        $cells = str_getcsv($line, ';', '"', '\\');
        $this->assertSame((string) $absent, $cells[count($cells) - 3], 'Absences without a reason equal the journal');
        $this->assertSame(200, $this->as('admin')->get("/reports/attendance?group={$group}&semester={$semester}&format=print")['status']);
    }

    public function testPerformanceScope(): void
    {
        $group = $this->group('ПО-26-1');
        $semester = $this->semester();
        $all = (int) $this->value('SELECT COUNT(*) FROM teacher_subject_assignments WHERE group_id = ? AND semester_id = ?', [$group, $semester]);
        $count = static fn (string $body): int => preg_match_all('/<th class="text-center" title=/', $body);
        $admin = $this->as('admin')->get("/reports/performance?group={$group}&semester={$semester}");
        $this->assertSame($all, $count($admin['body']), 'Administration sees every subject');
        $teacher = (string) $this->value('SELECT u.login FROM teacher_subject_assignments a JOIN teachers t ON t.id = a.teacher_id JOIN users u ON u.id = t.user_id
            WHERE a.group_id = ? AND a.semester_id = ? AND t.id NOT IN (SELECT COALESCE(curator_teacher_id, 0) FROM `groups` WHERE id = ?)
            AND t.id NOT IN (SELECT COALESCE(head_teacher_id, 0) FROM departments) LIMIT 1', [$group, $semester, $group]);
        $own = (int) $this->value('SELECT COUNT(*) FROM teacher_subject_assignments a JOIN teachers t ON t.id = a.teacher_id JOIN users u ON u.id = t.user_id WHERE a.group_id = ? AND a.semester_id = ? AND u.login = ?', [$group, $semester, $teacher]);
        $this->assertSame($own, $count($this->as($teacher)->get("/reports/performance?group={$group}&semester={$semester}")['body']), "Teacher {$teacher} sees only own subjects");
        $this->assertSame(403, $this->as('omarov.dm')->get("/reports/performance?group={$group}&semester={$semester}")['status'], 'Head of another department');
        $this->assertSame(403, $this->as('qasymova.i')->get('/reports/performance')['status'], 'Students');
    }

    public function testTeacherPagesAndGradesAddress(): void
    {
        $teacher = $this->as('nurlanova.ze');
        $groups = $teacher->get('/groups/my');
        $this->assertSame(200, $groups['status']);
        $this->assertStringContainsString('ПО-26-1', $groups['body']);
        $subjects = $teacher->get('/journal/subjects');
        $this->assertSame(200, $subjects['status']);
        $this->assertTrue(preg_match_all('#href="/journal/\d+"#', $subjects['body']) >= 1, 'Journals are linked');
        $student = $this->as('qasymova.i');
        $this->assertSame('/grades', $student->get('/journal/my')['location'], 'Old address redirects');
        $this->assertSame(200, $student->get('/grades')['status']);
    }
}
