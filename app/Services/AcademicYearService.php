<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\AcademicYearRepository;
use DateTimeImmutable;

/**
 * Academic years and their two semesters.
 *
 * Rules: the year lasts at most 13 months and does not overlap other years; semesters lie
 * inside the year and semester 1 ends before semester 2 starts; the title "YYYY-YYYY" is
 * derived from the dates. Archived years are read-only; the active year cannot be deleted
 * or archived; a year with groups or calendar events cannot be deleted.
 */
final class AcademicYearService
{
    public function __construct(private readonly AcademicYearRepository $years = new AcademicYearRepository())
    {
    }

    /**
     * @param array<string, string> $data start_date, end_date, s1_start, s1_end, s2_start, s2_end
     */
    public function save(?array $year, array $data): int
    {
        if ($year !== null && $year['status'] === 'archived') {
            throw new ValidationException(['start_date' => [__('years.archived_readonly')]]);
        }
        $this->check($year !== null ? (int) $year['id'] : null, $data);
        $title = substr($data['start_date'], 0, 4) . '-' . substr($data['end_date'], 0, 4);

        $id = Database::transaction(function () use ($year, $data, $title): int {
            $id = $year !== null ? (int) $year['id'] : $this->years->create($title, $data['start_date'], $data['end_date']);
            if ($year !== null) {
                $this->years->update($id, $title, $data['start_date'], $data['end_date']);
            }
            $this->years->saveSemester($id, 1, $data['s1_start'], $data['s1_end']);
            $this->years->saveSemester($id, 2, $data['s2_start'], $data['s2_end']);

            return $id;
        });

        AuditLogger::log($year === null ? 'years.created' : 'years.updated', 'academic_years', $id,
            $year !== null ? ['title' => $year['title'], 'start_date' => $year['start_date'], 'end_date' => $year['end_date']] : null,
            ['title' => $title] + $data
        );

        return $id;
    }

    /**
     * Make the year active. The previously active year is archived if it has already
     * ended (or ends before the new one starts), otherwise it returns to "planned".
     */
    public function activate(array $year): void
    {
        if ($year['status'] === 'active') {
            return;
        }
        $previous = $this->years->active();

        Database::transaction(function () use ($year, $previous): void {
            if ($previous !== null) {
                $finished = $previous['end_date'] < date('Y-m-d') || $previous['end_date'] < $year['start_date'];
                $this->years->setStatus((int) $previous['id'], $finished ? 'archived' : 'planned');
            }
            $this->years->setStatus((int) $year['id'], 'active');
        });

        AuditLogger::log('years.activated', 'academic_years', (int) $year['id'],
            $previous !== null ? ['active' => $previous['title']] : null, ['active' => $year['title']]);
    }

    public function archive(array $year): void
    {
        if ($year['status'] === 'active') {
            throw new ValidationException(['status' => [__('years.cannot_archive_active')]]);
        }
        $this->years->setStatus((int) $year['id'], 'archived');
        AuditLogger::log('years.archived', 'academic_years', (int) $year['id']);
    }

    public function restore(array $year): void
    {
        if ($year['status'] !== 'archived') {
            return;
        }
        $this->years->setStatus((int) $year['id'], 'planned');
        AuditLogger::log('years.restored', 'academic_years', (int) $year['id']);
    }

    public function delete(array $year): void
    {
        if ($year['status'] === 'active' || $this->years->usageCount((int) $year['id']) > 0) {
            throw new ValidationException(['status' => [__('years.cannot_delete')]]);
        }
        Database::transaction(fn () => $this->years->delete((int) $year['id']));
        AuditLogger::log('years.deleted', 'academic_years', (int) $year['id'], ['title' => $year['title']]);
    }

    /** @param array<string, string> $d */
    private function check(?int $id, array $d): void
    {
        $errors = [];
        $start = new DateTimeImmutable($d['start_date']);
        $end = new DateTimeImmutable($d['end_date']);

        if ($end <= $start) {
            $errors['end_date'][] = __('years.end_after_start');
        } elseif ($start->modify('+13 months') < $end) {
            $errors['end_date'][] = __('years.too_long');
        } elseif ((int) $end->format('Y') !== (int) $start->format('Y') + 1) {
            $errors['end_date'][] = __('years.span_two_years');
        }

        foreach ([1, 2] as $n) {
            $s = $d["s{$n}_start"];
            $e = $d["s{$n}_end"];
            if ($e <= $s) {
                $errors["s{$n}_end"][] = __('years.end_after_start');
            } elseif ($s < $d['start_date'] || $e > $d['end_date']) {
                $errors["s{$n}_start"][] = __('years.semester_inside');
            }
        }
        if (!isset($errors['s1_end']) && !isset($errors['s2_start']) && $d['s2_start'] <= $d['s1_end']) {
            $errors['s2_start'][] = __('years.semesters_order');
        }

        if ($errors === []) {
            $title = substr($d['start_date'], 0, 4) . '-' . substr($d['end_date'], 0, 4);
            if ($this->years->titleTaken($title, $id)) {
                $errors['start_date'][] = __('years.title_taken', ['title' => $title]);
            } elseif ($this->years->overlaps($d['start_date'], $d['end_date'], $id)) {
                $errors['start_date'][] = __('years.overlap');
            } elseif ($id !== null && ($outside = $this->years->eventsOutside($id, $d['start_date'], $d['end_date'])) > 0) {
                $errors['start_date'][] = __('years.events_outside', ['count' => $outside]);
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
