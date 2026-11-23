<?php

declare(strict_types=1);

namespace Tests\Integration;

use ZipArchive;

/**
 * Bulk import from Excel: preview with checks, import of valid rows only, one-time credentials.
 */
final class ImportTest extends IntegrationTestCase
{
    private float $started = 0.0;

    public function setUp(): void
    {
        $this->started = microtime(true) - 1;
    }

    /** Previews left by the tests (not imported) are removed right away. */
    public function tearDown(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/storage/private/imports/*.json') ?: [] as $f) {
            if (filemtime($f) >= (int) $this->started) {
                @unlink($f);
            }
        }
    }

    /** A minimal real .xlsx (inline strings; numbers stay numbers, e.g. Excel dates). */
    private function xlsx(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $r => $cells) {
            $sheet .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($cells) as $c => $v) {
                $ref = chr(65 + $c) . ($r + 1);
                $sheet .= is_int($v) ? "<c r=\"{$ref}\"><v>{$v}</v></c>" : "<c r=\"{$ref}\" t=\"inlineStr\"><is><t>" . htmlspecialchars((string) $v, ENT_XML1) . '</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Лист1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return $file;
    }

    /** Valid IIN with a correct check digit. */
    private function iin(string $eleven): string
    {
        $d = array_map('intval', str_split($eleven));
        foreach ([[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11], [3, 4, 5, 6, 7, 8, 9, 10, 11, 1, 2]] as $w) {
            $c = array_sum(array_map(static fn ($x, $y) => $x * $y, $d, $w)) % 11;
            if ($c !== 10) {
                return $eleven . $c;
            }
        }

        return $this->iin(substr($eleven, 0, 10) . (string) (((int) $eleven[10] + 1) % 10));
    }

    public function testStudentsFromExcel(): void
    {
        $iin = $this->iin('08030550' . random_int(100, 999));
        $file = $this->xlsx([
            ['Фамилия', 'Имя', 'Отчество', 'ИИН', 'Дата рождения', 'Пол', 'Группа', 'Финансирование', 'Лишняя колонка'],
            ['Тестов', 'Импорт', 'Первый', $iin, 39512, 'М', 'ПО-26-1', 'грант', 'x'],          // 39512 = 2008-03-05 as an Excel date
            ['Ошибкина', 'Анна', '', '123456789012', '05.03.2008', 'Ж', 'ПО-26-1', 'платно', ''],   // bad IIN
            ['Нетгруппы', 'Олег', '', '', '', '', 'НЕТ-99', '', ''],                               // unknown group
            ['Дубль', 'Иин', '', $iin, '', '', 'ПО-26-1', '', ''],                                 // same IIN as row 2
        ]);
        $admin = $this->as('admin');
        $students = (int) $this->value('SELECT COUNT(*) FROM students');
        $r = $admin->upload('/import/students', [], 'file', $file, 'students.xlsx');
        $this->assertMatchesRegularExpression('#^/import/students/preview/[a-f0-9]{32}$#', $r['location'], 'Preview page');
        $this->assertSame($students, (int) $this->value('SELECT COUNT(*) FROM students'), 'Preview does not write');
        $this->assertStringContainsString('ИИН', $r['body']);
        $this->assertStringContainsString('НЕТ-99', $r['body']);
        $this->assertStringContainsString('Лишняя колонка', $r['body'], 'Unknown column is reported');
        $this->assertSame(3, substr_count($r['body'], 'table-danger'), 'Three rows with errors');

        $result = $admin->submit($r['location'], []);
        $this->assertSame('/import/students/result', $result['location']);
        $this->assertSame($students + 1, (int) $this->value('SELECT COUNT(*) FROM students'), 'Only the valid row is imported');
        $row = $this->rows("SELECT s.birth_date, s.gender, s.funding, g.code, u.must_change_password, u.login FROM students s JOIN users u ON u.id = s.user_id JOIN `groups` g ON g.id = s.current_group_id WHERE s.iin = ?", [$iin])[0];
        $this->assertSame(['2008-03-05', 'male', 'state_grant', 'ПО-26-1', 1], [$row['birth_date'], $row['gender'], $row['funding'], $row['code'], (int) $row['must_change_password']]);
        $this->assertStringContainsString($row['login'], $result['body'], 'Credentials are shown');
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM student_group_history h JOIN students s ON s.id = h.student_id WHERE s.iin = ? AND h.reason = 'enrollment'", [$iin]), 'Enrollment history');

        $csv = $admin->request('POST', '/import/students/result/download', []);
        $this->assertStringContainsString($row['login'], $csv['body']);
        $this->assertSame(404, $admin->request('POST', '/import/students/result/download', [])['status'], 'Credentials are downloadable once');
        $admin->submit($r['location'], []);
        $this->assertSame($students + 1, (int) $this->value('SELECT COUNT(*) FROM students'), 'A preview cannot be imported twice');
        @unlink($file);
    }

    public function testScopeAndPermissions(): void
    {
        $file = $this->xlsx([['Фамилия', 'Имя', 'Группа'], ['Чужой', 'Студент', 'ПО-26-1']]);
        $headEco = $this->as('omarov.dm');
        $r = $headEco->upload('/import/students', [], 'file', $file, 'a.xlsx');
        $this->assertStringContainsString('вне вашей области доступа', $r['body'], 'Head of ECO cannot import into an IT group');
        $this->assertSame(403, $this->as('nurlanova.ze')->get('/import/students')['status'], 'Teacher');
        $this->assertSame(403, $this->as('qasymova.i')->get('/import/teachers')['status'], 'Student');
        $fake = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($fake, 'not a spreadsheet');
        $r = $this->as('admin')->upload('/import/students', [], 'file', $fake, 'fake.xlsx');
        $this->assertSame('/import/students', $r['location'], 'Unreadable file goes back to the form');
        @unlink($file);
        @unlink($fake);
    }

    public function testTeachersFromCsv(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'imp') . '.csv';
        $tag = (string) random_int(1000, 9999);
        file_put_contents($file, "\xEF\xBB\xBFФамилия;Имя;Отделение;Должность;Табельный номер\nПреподов;Тест;IT;Преподаватель;T{$tag}\nБезымянный;;IT;;\n");
        $admin = $this->as('admin');
        $r = $admin->upload('/import/teachers', [], 'file', $file, 'teachers.csv');
        $this->assertSame(1, substr_count($r['body'], 'table-danger'), 'Row without a first name');
        $admin->submit($r['location'], []);
        $this->assertSame('IT', (string) $this->value('SELECT d.code FROM teachers t JOIN departments d ON d.id = t.department_id WHERE t.employee_number = ?', ["T{$tag}"]));
        @unlink($file);
    }
}
