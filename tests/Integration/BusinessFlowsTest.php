<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\GradeCalculator;

/**
 * Main processes end to end: journal, final grades, requests and certificates with QR check,
 * anonymous surveys, workload limits, parents.
 */
final class BusinessFlowsTest extends IntegrationTestCase
{
    private function journal(string $group, string $subject): int
    {
        return (int) $this->value('SELECT a.id FROM teacher_subject_assignments a JOIN `groups` g ON g.id = a.group_id JOIN subjects s ON s.id = a.subject_id WHERE g.code = ? AND s.code = ?', [$group, $subject]);
    }

    public function testJournalGradeValidationAndHistory(): void
    {
        $journal = $this->journal('ПО-26-1', 'ООД 05');
        $lesson = (int) $this->value('SELECT id FROM journal_lessons WHERE assignment_id = ? AND lesson_date <= CURDATE() ORDER BY lesson_date DESC LIMIT 1', [$journal]);
        $student = $this->studentId('qasymova.i');
        $teacher = $this->as((string) $this->value('SELECT u.login FROM teacher_subject_assignments a JOIN teachers t ON t.id = a.teacher_id JOIN users u ON u.id = t.user_id WHERE a.id = ?', [$journal]));
        $cell = static fn (string $v): array => ['kind' => 'lesson', 'lesson_id' => $lesson, 'student_id' => $student, 'value' => $v];
        $this->assertSame(422, $teacher->json('POST', "/api/journals/{$journal}/grades", $cell('150'))['status'], 'Out of the scheme scale');
        $this->assertSame(422, $teacher->json('POST', "/api/journals/{$journal}/grades", $cell('abc'))['status']);
        $history = (int) $this->value('SELECT COUNT(*) FROM grade_change_history WHERE assignment_id = ?', [$journal]);
        $this->assertSame(200, $teacher->json('POST', "/api/journals/{$journal}/grades", $cell('87,5'))['status']);
        $this->assertSame('87.50', (string) $this->value('SELECT value FROM journal_grades WHERE journal_lesson_id = ? AND student_id = ?', [$lesson, $student]));
        $this->assertSame($history + 1, (int) $this->value('SELECT COUNT(*) FROM grade_change_history WHERE assignment_id = ?', [$journal]), 'Change is logged');
        $this->assertSame(403, $this->as('qasymova.i')->json('POST', "/api/journals/{$journal}/grades", $cell('100'))['status'], 'Students cannot grade');
        $this->assertSame(403, $this->as('omarov.dm')->json('GET', "/api/journals/{$journal}")['status'], 'Other department');
    }

    public function testFinalGradeMatchesTheScheme(): void
    {
        $journal = $this->journal('ПО-26-1', 'ООД 05');
        $scheme = $this->rows('SELECT gs.final_decimals FROM teacher_subject_assignments a JOIN grading_schemes gs ON gs.id = a.grading_scheme_id WHERE a.id = ?', [$journal])[0] ?? null;
        $this->assertNotNull($scheme, 'Journal has a scheme');
        $components = $this->rows(
            'SELECT t.code, t.id AS type_id, c.weight FROM teacher_subject_assignments a JOIN grading_scheme_components c ON c.grading_scheme_id = a.grading_scheme_id
             JOIN assessment_types t ON t.id = c.assessment_type_id WHERE a.id = ? ORDER BY c.sort_order',
            [$journal]
        );
        $data = $this->as('admin')->json('GET', "/api/journals/{$journal}")['data']['data'] ?? [];
        $checked = 0;
        foreach ($data['students'] ?? [] as $s) {
            $values = [];
            foreach ($components as $c) {
                $v = $c['code'] === 'current' ? ($s['result']['current'] ?? null) : ($s['result']['components'][(int) $c['type_id']] ?? null);
                $values[$c['code']] = $v === null ? null : (float) $v;
            }
            $expected = GradeCalculator::final(['final_decimals' => (int) $scheme['final_decimals'], 'components' => $components], $values)['value'];
            $actual = $s['result']['final'] ?? null;
            $this->assertEquals($expected, $actual === null ? null : (float) $actual, 'Final of student ' . $s['id']);
            $checked++;
        }
        $this->assertTrue($checked > 0, 'Students were checked');
    }

    public function testCertificateRequestApprovalAndPublicCheck(): void
    {
        $student = $this->as('qasymova.i');
        $type = (int) $this->value("SELECT id FROM document_types WHERE code = 'study_certificate'");
        $count = (int) $this->value('SELECT COUNT(*) FROM document_requests');
        $student->submit('/documents', ['type_id' => $type, 'action' => 'submit', 'purpose' => '']);
        $this->assertSame($count, (int) $this->value('SELECT COUNT(*) FROM document_requests'), 'Without a purpose nothing is submitted');
        $student->submit('/documents', ['type_id' => $type, 'action' => 'submit', 'purpose' => 'по месту требования', 'copies' => 1]);
        $id = (int) $this->value('SELECT MAX(id) FROM document_requests');
        $this->assertMatchesRegularExpression('/^З-\d{4}-\d{5}$/u', (string) $this->value('SELECT number FROM document_requests WHERE id = ?', [$id]));
        $office = $this->as('t.office', 'academic_office');
        $this->assertSame(403, $this->as('petrov.av')->get("/documents/{$id}")['status'], 'Curator does not process requests');
        $office->submit("/documents/{$id}/status", ['action' => 'take']);
        $office->submit("/documents/{$id}/status", ['action' => 'approve']);
        $this->assertSame('approved', $this->value('SELECT status FROM document_requests WHERE id = ?', [$id]));
        $code = (string) $this->value('SELECT verification_code FROM generated_documents WHERE request_id = ?', [$id]);
        $this->assertSame(32, strlen($code));
        $page = (new Http())->get('/verify/' . $code)['body'];
        $this->assertStringContainsString('С-', $page);
        $this->assertStringNotContainsString('Қасымова', $page, 'No personal data on the public check');
        $this->assertSame(200, $student->get("/documents/{$id}/certificate")['status']);
        $this->assertSame(403, $this->as('qasymov.e')->get("/documents/{$id}/certificate")['status'], 'Another student');
    }

    public function testAnonymousSurveyKeepsNoIdentity(): void
    {
        $this->db()->prepare("INSERT INTO surveys (title_ru, audience, is_anonymous, starts_on, ends_on, status, published_at) VALUES ('Тест', 'students', 1, CURDATE(), CURDATE() + INTERVAL 5 DAY, 'published', NOW())")->execute();
        $survey = (int) $this->db()->lastInsertId();
        $this->db()->prepare("INSERT INTO survey_questions (survey_id, type, text_ru, is_required, scale_min, scale_max, sort_order) VALUES (?, 'scale', 'Оценка', 1, 1, 5, 1)")->execute([$survey]);
        $question = (int) $this->db()->lastInsertId();
        $student = $this->as('qasymova.i');
        $student->submit("/surveys/{$survey}/answers", ['answers' => [$question => 4]]);
        $student->submit("/surveys/{$survey}/answers", ['answers' => [$question => 1]]);
        $rows = $this->rows('SELECT user_id, group_id, submitted_at FROM survey_responses WHERE survey_id = ?', [$survey]);
        $this->assertCount(1, $rows, 'One answer per person');
        $this->assertSame([null, null, null], array_values($rows[0]), 'No user, group or time stored');
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM survey_participants WHERE survey_id = ?', [$survey]));
        $this->assertSame(403, $this->as('nurlanova.ze')->get("/surveys/{$survey}")['status'], 'Not for teachers');
    }

    public function testWorkloadCannotExceedTheCurriculum(): void
    {
        $semester = (int) $this->value("SELECT id FROM semesters WHERE academic_year_id = (SELECT id FROM academic_years WHERE status = 'active') AND number = 1");
        $plan = (int) $this->value("SELECT p.id FROM workload_plans p WHERE p.status = 'draft' ORDER BY p.id LIMIT 1");
        $type = (int) $this->value("SELECT id FROM workload_types WHERE code = 'practice'");
        $group = (int) $this->value("SELECT id FROM `groups` WHERE code = 'ПО-26-1'");
        $subject = (int) $this->value("SELECT id FROM subjects WHERE code = 'ООД 05'");
        $cap = (int) $this->value('SELECT cs.hours_practice FROM curriculum_subjects cs JOIN `groups` g ON g.curriculum_id = cs.curriculum_id WHERE g.id = ? AND cs.subject_id = ? AND cs.semester = 1', [$group, $subject]);
        $used = (int) $this->value('SELECT COALESCE(SUM(hours), 0) FROM workload_assignments WHERE semester_id = ? AND group_id = ? AND subject_id = ? AND workload_type_id = ?', [$semester, $group, $subject, $type]);
        $office = $this->as('t.office', 'academic_office');
        $office->submit("/workload/plans/{$plan}/assignments", ['semester_id' => $semester, 'group_id' => $group, 'subject_id' => $subject, 'workload_type_id' => $type, 'hours' => $cap - $used + 1]);
        $this->assertTrue($cap > 0, 'Curriculum line exists');
        $this->assertSame($used, (int) $this->value('SELECT COALESCE(SUM(hours), 0) FROM workload_assignments WHERE semester_id = ? AND group_id = ? AND subject_id = ? AND workload_type_id = ?', [$semester, $group, $subject, $type]), "Nothing over the curriculum ({$cap} h, used {$used})");
        $office->submit("/workload/plans/{$plan}/assignments", ['semester_id' => $semester, 'workload_type_id' => (int) $this->value("SELECT id FROM workload_types WHERE code = 'other'"), 'hours' => 2, 'note' => 'test']);
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM workload_assignments WHERE plan_id = ? AND note = 'test'", [$plan]), 'A line within the limits is saved');
        $this->assertSame(403, $this->as('nurlanova.ze')->submit("/workload/plans/{$plan}/assignments", ['semester_id' => $semester, 'workload_type_id' => $type, 'hours' => 1])['status'], 'Teachers do not plan');
    }

    public function testParentSeesOnlyOwnChild(): void
    {
        $child = $this->studentId('qasymov.e');
        $admin = $this->as('admin');
        $admin->submit("/students/{$child}/guardians", ['mode' => 'new', 'relation' => 'father', 'last_name' => 'Тестов', 'first_name' => 'Отец', 'locale' => 'ru']);
        $login = (string) $this->value("SELECT u.login FROM student_guardians sg JOIN users u ON u.id = sg.user_id WHERE sg.student_id = ? ORDER BY sg.created_at DESC LIMIT 1", [$child]);
        $this->assertSame('1', (string) $this->value('SELECT must_change_password FROM users WHERE login = ?', [$login]), 'Temporary password must be changed');
        $parent = $this->as($login);
        $this->assertSame(200, $parent->get("/children/{$child}")['status']);
        $this->assertSame(403, $parent->get('/children/' . $this->studentId('qasymova.i'))['status']);
        $this->assertSame(403, $parent->get("/students/{$child}")['status']);
    }
}
