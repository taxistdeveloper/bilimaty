<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\JournalRepository;
use App\Repositories\ScheduleRepository;

/**
 * The signed-in teacher's teaching of the current semester: journals grouped by group
 * ("My groups", plus the groups they curate) and by subject ("My subjects").
 */
final class TeachingService
{
    /** @return list<array<string, mixed>> journals of the teacher in the semester (null = current) */
    public function journals(int $teacherId, ?int $semesterId = null): array
    {
        $semesterId ??= (int) ((new ScheduleRepository())->semesterOn(date('Y-m-d'))['id'] ?? 0);

        return $semesterId === 0 ? [] : (new JournalRepository())->list(['semester' => $semesterId], $teacherId, true);
    }

    /** @return list<array<string, mixed>> group => code, course, students, curated, journals */
    public function groups(int $teacherId): array
    {
        $out = [];
        foreach ($this->journals($teacherId) as $j) {
            $out[(int) $j['group_id']] ??= ['id' => (int) $j['group_id'], 'code' => $j['group_code'], 'course' => $j['course'], 'curated' => false, 'journals' => []];
            $out[(int) $j['group_id']]['journals'][] = $j;
        }
        foreach (Database::fetchAll("SELECT id, code, course FROM `groups` WHERE curator_teacher_id = ? AND status = 'active'", [$teacherId]) as $g) {
            $out[(int) $g['id']] ??= ['id' => (int) $g['id'], 'code' => $g['code'], 'course' => $g['course'], 'curated' => false, 'journals' => []];
            $out[(int) $g['id']]['curated'] = true;
        }
        $ids = array_keys($out);
        if ($ids !== []) {
            foreach (Database::fetchAll(
                "SELECT current_group_id AS id, COUNT(*) AS n FROM students WHERE status = 'active' AND current_group_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ') GROUP BY current_group_id',
                $ids
            ) as $r) {
                $out[(int) $r['id']]['students'] = (int) $r['n'];
            }
        }
        uasort($out, static fn (array $a, array $b): int => strcmp((string) $a['code'], (string) $b['code']));

        return array_values($out);
    }

    /** @return list<array<string, mixed>> subject => code, name, journals */
    public function subjects(int $teacherId): array
    {
        $out = [];
        foreach ($this->journals($teacherId) as $j) {
            $out[(int) $j['subject_id']] ??= ['code' => $j['subject_code'], 'name' => localized(['name_ru' => $j['subject_name_ru'], 'name_kk' => $j['subject_name_kk']]), 'journals' => []];
            $out[(int) $j['subject_id']]['journals'][] = $j;
        }
        uasort($out, static fn (array $a, array $b): int => strcmp((string) $a['code'], (string) $b['code']));

        return array_values($out);
    }
}
