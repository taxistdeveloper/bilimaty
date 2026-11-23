<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\CurriculumRepository;

/**
 * Curriculum lifecycle: draft → approved (read-only) → archived.
 * Only drafts are edited; a new intake gets a copy of an existing plan.
 */
final class CurriculumService
{
    public function __construct(private readonly CurriculumRepository $plans = new CurriculumRepository())
    {
    }

    /** @param array<string, mixed> $data */
    public function save(?array $plan, array $data): int
    {
        if ($plan !== null && $plan['status'] !== 'draft') {
            throw new ValidationException(['title' => [__('curricula.only_draft')]]);
        }
        if ($this->plans->cohortExists($data, $plan !== null ? (int) $plan['id'] : null)) {
            throw new ValidationException(['admission_year' => [__('curricula.cohort_exists')]]);
        }
        if ($plan !== null && $this->plans->maxItemSemester((int) $plan['id']) > (int) $data['semesters_count']) {
            throw new ValidationException(['semesters_count' => [__('curricula.semesters_in_use')]]);
        }

        $id = $plan === null ? $this->plans->create($data) : (int) $plan['id'];
        if ($plan !== null) {
            $this->plans->update($id, $data);
        }
        AuditLogger::log($plan === null ? 'curricula.created' : 'curricula.updated', 'curricula', $id,
            $plan !== null ? array_intersect_key($plan, $data) : null, $data);

        return $id;
    }

    /** @param array<string, mixed> $plan */
    public function approve(array $plan): void
    {
        if ($plan['status'] !== 'draft') {
            throw new ValidationException(['status' => [__('curricula.only_draft')]]);
        }
        if ((int) $plan['items_count'] === 0) {
            throw new ValidationException(['status' => [__('curricula.empty_cannot_approve')]]);
        }
        $this->plans->setStatus((int) $plan['id'], 'approved', Auth::id());
        AuditLogger::log('curricula.approved', 'curricula', (int) $plan['id']);
    }

    /** Return an approved plan to draft (e.g. to fix a mistake); archived plans go back to approved. */
    public function reopen(array $plan): void
    {
        $to = match ($plan['status']) {
            'approved' => 'draft',
            'archived' => 'approved',
            default => throw new ValidationException(['status' => [__('curricula.only_draft')]]),
        };
        $this->plans->setStatus((int) $plan['id'], $to, null);
        AuditLogger::log('curricula.reopened', 'curricula', (int) $plan['id'], ['status' => $plan['status']], ['status' => $to]);
    }

    public function archive(array $plan): void
    {
        if ($plan['status'] !== 'approved') {
            throw new ValidationException(['status' => [__('curricula.only_approved_archive')]]);
        }
        $this->plans->setStatus((int) $plan['id'], 'archived', null);
        AuditLogger::log('curricula.archived', 'curricula', (int) $plan['id']);
    }

    public function delete(array $plan): void
    {
        if ($plan['status'] !== 'draft' || (int) $plan['groups_count'] > 0) {
            throw new ValidationException(['status' => [__('curricula.cannot_delete')]]);
        }
        $this->plans->delete((int) $plan['id']);
        AuditLogger::log('curricula.deleted', 'curricula', (int) $plan['id'], ['title' => $plan['title']]);
    }

    /** Copy a plan (with all rows) as a draft for another intake year. */
    public function copy(array $plan, int $admissionYear, string $title): int
    {
        $data = [
            'specialty_id' => (int) $plan['specialty_id'],
            'qualification_id' => $plan['qualification_id'] !== null ? (int) $plan['qualification_id'] : null,
            'title' => $title,
            'admission_year' => $admissionYear,
            'study_form' => $plan['study_form'],
            'base_education' => $plan['base_education'],
            'semesters_count' => (int) $plan['semesters_count'],
            'note' => $plan['note'],
        ];
        if ($this->plans->cohortExists($data, null)) {
            throw new ValidationException(['admission_year' => [__('curricula.cohort_exists')]]);
        }

        $id = Database::transaction(function () use ($plan, $data): int {
            $id = $this->plans->create($data);
            $this->plans->copyItems((int) $plan['id'], $id);

            return $id;
        });
        AuditLogger::log('curricula.copied', 'curricula', $id, ['from' => (int) $plan['id']], ['admission_year' => $admissionYear]);

        return $id;
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $data validated row
     */
    public function saveItem(array $plan, ?int $itemId, array $data): void
    {
        if ($plan['status'] !== 'draft') {
            throw new ValidationException(['subject_id' => [__('curricula.only_draft')]]);
        }
        $errors = [];
        if ((int) $data['semester'] > (int) $plan['semesters_count']) {
            $errors['semester'][] = __('curricula.semester_out_of_plan', ['count' => $plan['semesters_count']]);
        }
        if ((int) $data['hours_theory'] + (int) $data['hours_practice'] + (int) $data['hours_lab'] > (int) $data['hours_total']) {
            $errors['hours_total'][] = __('curricula.hours_sum');
        }
        if ($this->plans->itemExists((int) $plan['id'], (int) $data['subject_id'], (int) $data['semester'], $itemId)) {
            $errors['subject_id'][] = __('curricula.item_exists');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $id = $this->plans->saveItem((int) $plan['id'], $itemId, $data);
        AuditLogger::log($itemId === null ? 'curricula.item_added' : 'curricula.item_updated', 'curricula', (int) $plan['id'], null, ['item' => $id] + $data);
    }

    public function deleteItem(array $plan, int $itemId): void
    {
        if ($plan['status'] !== 'draft') {
            throw new ValidationException(['subject_id' => [__('curricula.only_draft')]]);
        }
        $this->plans->deleteItem((int) $plan['id'], $itemId);
        AuditLogger::log('curricula.item_deleted', 'curricula', (int) $plan['id'], ['item' => $itemId]);
    }
}
