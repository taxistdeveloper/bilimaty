<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Policies\JournalPolicy;
use App\Repositories\JournalRepository;
use App\Services\JournalService;

/**
 *   GET  /api/journals/{id}         journal grid (lessons, students, cells, results)
 *   POST /api/journals/{id}/grades  autosave of one cell:
 *        {kind: "lesson", lesson_id, student_id, value}       grade or н/у
 *        {kind: "comment", lesson_id, student_id, comment}
 *        {kind: "assessment", type_id, student_id, value}     manual component
 *   → {data: {student_id, result}} or 422 {errors: {value: [...]}}
 */
final class JournalController extends Controller
{
    private JournalRepository $journals;
    private JournalPolicy $policy;

    public function __construct()
    {
        $this->journals = new JournalRepository();
        $this->policy = new JournalPolicy($this->journals);
    }

    public function show(int $id): Response
    {
        $journal = $this->journals->find($id) ?? throw new HttpException(404);
        if (!$this->policy->view($journal)) {
            throw new HttpException(403);
        }
        $grid = (new JournalService($this->journals))->grid($journal);

        return $this->json([
            'id' => $id,
            'status' => $journal['status'],
            'lessons' => array_map(static fn (array $l): array => [
                'id' => (int) $l['id'], 'date' => $l['lesson_date'], 'period' => (int) $l['period_number'], 'topic' => $l['topic'], 'homework' => $l['homework'],
            ], $grid['lessons']),
            'students' => array_map(static fn (array $s): array => [
                'id' => (int) $s['id'], 'name' => full_name($s), 'cells' => $s['cells'], 'result' => $s['result'],
            ], $grid['students']),
        ]);
    }

    public function save(Request $request, int $id): Response
    {
        $journal = $this->journals->find($id) ?? throw new HttpException(404);
        if (!$this->policy->edit($journal)) {
            throw new HttpException(403, __('journal.locked'));
        }
        $data = $this->validate($request, [
            'kind' => 'required|in:lesson,comment,assessment',
            'student_id' => 'required|integer',
            'lesson_id' => 'nullable|integer',
            'type_id' => 'nullable|integer',
            'value' => 'nullable|string|max:20',
            'comment' => 'nullable|string|max:255',
        ]);
        $service = new JournalService($this->journals);
        $studentId = (int) $data['student_id'];

        $result = match ($data['kind']) {
            'lesson' => $service->setLessonCell($journal, $this->lesson($id, $data), $studentId, (string) ($request->input('value') ?? '')),
            'comment' => $service->setComment($journal, $this->lesson($id, $data), $studentId, (string) ($request->input('comment') ?? '')),
            'assessment' => $service->setAssessment($journal, (int) ($data['type_id'] ?? 0), $studentId, (string) ($request->input('value') ?? '')),
        };

        return $this->json(['student_id' => $studentId, 'result' => $result], __('journal.saved'));
    }

    /** @param array<string, mixed> $data */
    private function lesson(int $journalId, array $data): array
    {
        return $this->journals->lesson($journalId, (int) ($data['lesson_id'] ?? 0)) ?? throw new HttpException(404);
    }
}
