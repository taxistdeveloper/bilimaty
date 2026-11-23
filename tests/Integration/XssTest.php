<?php

declare(strict_types=1);

namespace Tests\Integration;

/**
 * Data typed by users is shown escaped on every page that displays it.
 */
final class XssTest extends IntegrationTestCase
{
    private const PAYLOAD = '<script>alert(1)</script>';

    public function testUserTextIsEscaped(): void
    {
        $student = $this->studentId('abdrakhmanova.a');
        $before = $this->value('SELECT last_name FROM users u JOIN students s ON s.user_id = u.id WHERE s.id = ?', [$student]);
        $this->db()->prepare('UPDATE users u JOIN students s ON s.user_id = u.id SET u.last_name = ? WHERE s.id = ?')->execute([self::PAYLOAD, $student]);
        $group = (int) $this->value('SELECT current_group_id FROM students WHERE id = ?', [$student]);
        $journal = (int) $this->value('SELECT id FROM teacher_subject_assignments WHERE group_id = ? LIMIT 1', [$group]);
        try {
            $admin = $this->as('admin');
            foreach (['/students', "/students/{$student}", "/groups/{$group}", "/journal/{$journal}", '/students?q=' . urlencode(self::PAYLOAD)] as $page) {
                $body = $admin->get($page)['body'];
                $this->assertStringNotContainsString(self::PAYLOAD, $body, "Raw script on {$page}");
            }
            $this->assertStringContainsString('&lt;script&gt;', $admin->get("/students/{$student}")['body'], 'Shown as text');
        } finally {
            $this->db()->prepare('UPDATE users u JOIN students s ON s.user_id = u.id SET u.last_name = ? WHERE s.id = ?')->execute([$before, $student]);
        }
    }
}
