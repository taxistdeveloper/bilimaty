<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth;
use App\Repositories\JournalRepository;
use App\Services\AccessScope;
use App\Services\Settings;

/**
 * Record-level rules for journals.
 *
 * - view:       the responsible teacher, a co-teacher (teaches this subject to the group by timetable),
 *               or anyone with journal.view whose AccessScope covers the group (curator, department head,
 *               college-wide roles). Students use "My grades" and see only their own row.
 * - edit marks: journal.manage + responsible/co-teacher, journal open (or approved when the
 *               "lock after approval" setting is off), academic year not archived.
 * - approve:    journal.approve within AccessScope.
 * - administer: create journals, change teacher/scheme, fill dates — schedule.manage or journal.approve
 *               within AccessScope.
 */
final class JournalPolicy
{
    private ?int $teacherId = null;
    private bool $teacherResolved = false;

    public function __construct(private readonly JournalRepository $journals = new JournalRepository())
    {
    }

    public function myTeacherId(): ?int
    {
        if (!$this->teacherResolved) {
            $this->teacherResolved = true;
            $this->teacherId = AccessScope::teacherId();
        }

        return $this->teacherId;
    }

    /** @param array<string, mixed> $journal */
    public function isTeacherOf(array $journal): bool
    {
        $me = $this->myTeacherId();

        return $me !== null && ((int) $journal['teacher_id'] === $me || $this->journals->isCoTeacher($journal, $me));
    }

    /** @param array<string, mixed> $journal */
    public function view(array $journal): bool
    {
        if (!Auth::canAny('journal.view', 'journal.manage')) {
            return false;
        }

        return $this->isTeacherOf($journal) || (Auth::can('journal.view') && AccessScope::allowsGroup((int) $journal['group_id']) && !$this->isStudentOnly());
    }

    /** @param array<string, mixed> $journal */
    public function edit(array $journal): bool
    {
        if (!Auth::can('journal.manage') || $journal['year_status'] === 'archived') {
            return false;
        }
        $editable = $journal['status'] === 'open'
            || ($journal['status'] === 'approved' && Settings::get('journal_lock_after_approval', '1') !== '1');

        return $editable && ($this->isTeacherOf($journal) || Auth::hasRole(Auth::SUPER_ADMIN));
    }

    /** @param array<string, mixed> $journal */
    public function submit(array $journal): bool
    {
        return $journal['status'] === 'open' && Auth::can('journal.manage') && $this->isTeacherOf($journal);
    }

    /** @param array<string, mixed> $journal */
    public function approve(array $journal): bool
    {
        return Auth::can('journal.approve') && AccessScope::allowsGroup((int) $journal['group_id']) && $journal['year_status'] !== 'archived';
    }

    /** @param array<string, mixed>|null $journal */
    public function administer(?array $journal = null): bool
    {
        if (!Auth::canAny('schedule.manage', 'journal.approve')) {
            return false;
        }

        return $journal === null || AccessScope::allowsGroup((int) $journal['group_id']);
    }

    /** A student account (no staff permissions on groups): must use "My grades". */
    private function isStudentOnly(): bool
    {
        return Auth::hasRole('student') && !Auth::can('groups.view');
    }
}
