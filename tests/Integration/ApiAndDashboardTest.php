<?php

declare(strict_types=1);

namespace Tests\Integration;

final class ApiAndDashboardTest extends IntegrationTestCase
{
    public function testWorkloadsApi(): void
    {
        $r = $this->as('t.office', 'academic_office')->json('GET', '/api/workloads?by=department');
        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['data']['success']);
        $this->assertTrue(count($r['data']['data']['rows']) >= 1, 'Rows by department');
        $this->assertSame(403, $this->as('qasymova.i')->json('GET', '/api/workloads')['status'], 'Students');
        $own = $this->as('nurlanova.ze')->json('GET', '/api/workloads?by=teacher');
        $this->assertSame(1, count($own['data']['data']['rows']), 'A teacher sees only own workload');
    }

    public function testDocumentRequestsApi(): void
    {
        $student = $this->as('qasymova.i');
        $type = (int) $this->value("SELECT id FROM document_types WHERE code = 'study_certificate'");
        $bad = $student->json('POST', '/api/document-requests', ['type_id' => $type]);
        $this->assertSame(422, $bad['status'], 'Purpose is required');
        $this->assertTrue(isset($bad['data']['errors']['purpose']));
        $ok = $student->json('POST', '/api/document-requests', ['type_id' => $type, 'purpose' => 'в банк', 'copies' => 1]);
        $this->assertSame(201, $ok['status']);
        $this->assertMatchesRegularExpression('/^З-\d{4}-\d{5}$/u', (string) $ok['data']['data']['number']);
        $this->assertSame('submitted', $ok['data']['data']['status']);
        $this->assertSame(403, $this->as('nurlanova.ze')->json('POST', '/api/document-requests', ['type_id' => $type, 'purpose' => 'x'])['status'], 'Teachers do not file requests');
    }

    public function testStudentAndTeacherDashboards(): void
    {
        $student = $this->as('qasymova.i')->get('/dashboard')['body'];
        $this->assertStringContainsString('Моя учёба', $student);
        $this->assertStringContainsString('Успеваемость за семестр', $student);
        $this->assertStringContainsString('ПО-26-1', $student);
        $teacher = $this->as('nurlanova.ze')->get('/dashboard')['body'];
        $this->assertStringContainsString('Мои журналы этого семестра', $teacher);
        $this->assertStringNotContainsString('Моя учёба', $teacher);
    }
}
