<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ScheduleRepository;
use App\Services\ScheduleService;
use DateTimeImmutable;

/**
 * Timetable JSON API.
 *   GET    /api/schedule?semester=&from=&to=[&group=|&teacher=|&classroom=]   schedule.view
 *   GET    /api/schedule/{id}                                                 schedule.view
 *   POST   /api/schedule            create a regular lesson                   schedule.manage
 *   PUT    /api/schedule/{id}       update it                                 schedule.manage
 *   DELETE /api/schedule/{id}       delete it                                 schedule.manage
 * Validation errors and conflicts come back as 422 {errors: {field: [...], conflicts: [...]}}.
 */
final class ScheduleController extends Controller
{
    private ScheduleRepository $repo;

    public function __construct()
    {
        $this->repo = new ScheduleRepository();
    }

    public function index(Request $request): Response
    {
        $semester = $this->repo->semester((int) $request->query('semester', 0)) ?? throw new HttpException(404);
        $from = $this->date((string) $request->query('from', '')) ?? (string) $semester['start_date'];
        $to = $this->date((string) $request->query('to', '')) ?? $from;
        if ($to < $from || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 31) {
            return $this->json(null, __('schedule.range_too_long'), 422);
        }
        $filter = [
            'group_id' => ctype_digit((string) $request->query('group', '')) ? (int) $request->query('group') : null,
            'teacher_id' => ctype_digit((string) $request->query('teacher', '')) ? (int) $request->query('teacher') : null,
            'classroom_id' => ctype_digit((string) $request->query('classroom', '')) ? (int) $request->query('classroom') : null,
        ];

        $items = array_map(static fn (array $o): array => [
            'entry_id' => $o['entry_id'],
            'date' => $o['date'],
            'period' => (int) $o['period_number'],
            'start' => substr((string) $o['start_time'], 0, 5),
            'end' => substr((string) $o['end_time'], 0, 5),
            'group' => $o['group_code'],
            'subgroup' => $o['subgroup'] !== null ? (int) $o['subgroup'] : null,
            'subject' => localized(['name_ru' => $o['subject_name_ru'], 'name_kk' => $o['subject_name_kk']]),
            'teacher' => $o['effective_teacher_name'],
            'classroom' => $o['effective_classroom_label'],
            'type' => $o['lesson_type'],
            'status' => $o['status'],
        ], (new ScheduleService($this->repo))->occurrences($semester, $from, $to, $filter));

        return $this->json($items);
    }

    public function show(int $id): Response
    {
        $entry = $this->repo->find($id) ?? throw new HttpException(404);

        return $this->json(array_intersect_key($entry, array_flip([
            'id', 'semester_id', 'group_id', 'subgroup', 'subject_id', 'teacher_id', 'classroom_id', 'weekday',
            'lesson_period_id', 'week_parity', 'lesson_type', 'note',
        ])) + ['changes_count' => $this->repo->changesCount($id)]);
    }

    public function store(Request $request): Response
    {
        $id = (new ScheduleService($this->repo))->saveEntry($this->validated($request));

        return $this->json(['id' => $id], __('schedule.saved'), 201);
    }

    public function update(Request $request, int $id): Response
    {
        $entry = $this->repo->find($id) ?? throw new HttpException(404);
        $data = $this->validated($request);
        if ((int) $data['semester_id'] !== (int) $entry['semester_id']) {
            return $this->json(null, __('schedule.semester_fixed'), 422, ['semester_id' => [__('schedule.semester_fixed')]]);
        }
        (new ScheduleService($this->repo))->saveEntry($data, $entry);

        return $this->json(['id' => $id], __('schedule.saved'));
    }

    public function destroy(int $id): Response
    {
        $entry = $this->repo->find($id) ?? throw new HttpException(404);
        (new ScheduleService($this->repo))->deleteEntry($entry);

        return $this->json(['id' => $id], __('schedule.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $this->validate($request, [
            'semester_id' => 'required|integer|exists:semesters,id',
            'group_id' => 'required|integer|exists:groups,id',
            'subgroup' => 'nullable|integer|between:1,4',
            'subject_id' => 'required|integer|exists:subjects,id',
            'teacher_id' => 'required|integer|exists:teachers,id',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'weekday' => 'required|integer|between:1,7',
            'lesson_period_id' => 'required|integer|exists:lesson_periods,id',
            'week_parity' => 'required|in:' . implode(',', ScheduleService::PARITIES),
            'lesson_type' => 'required|in:' . implode(',', ScheduleService::TYPES),
            'note' => 'nullable|string|max:255',
        ]);
        foreach (['semester_id', 'group_id', 'subject_id', 'teacher_id', 'weekday', 'lesson_period_id'] as $key) {
            $data[$key] = (int) $data[$key];
        }
        $data['subgroup'] = isset($data['subgroup']) && $data['subgroup'] !== '' ? (int) $data['subgroup'] : null;
        $data['classroom_id'] = isset($data['classroom_id']) && $data['classroom_id'] !== '' ? (int) $data['classroom_id'] : null;
        $data['note'] = ($data['note'] ?? '') !== '' ? (string) $data['note'] : null;

        return $data;
    }

    private function date(string $value): ?string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value ? $value : null;
    }
}
