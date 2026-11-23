<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Repositories\AcademicYearRepository;
use App\Repositories\ScheduleRepository;
use App\Services\HrService;
use App\Services\ScheduleService;
use DateTimeImmutable;

/**
 * Timetable pages: week/day grid by group, teacher or classroom, one-off changes
 * (substitution, room change, cancellation, rescheduling) and the changes journal.
 * Regular lessons are created/edited through the JSON API (modal + Fetch).
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
        $years = new AcademicYearRepository();
        $yearOptions = [];
        foreach ($years->allWithStats() as $y) {
            $yearOptions[(int) $y['id']] = str_replace('-', '–', (string) $y['title']) . ($y['status'] === 'active' ? ' ★' : '');
        }
        $today = date('Y-m-d');
        $requestedSemester = ctype_digit((string) $request->query('semester', '')) ? $this->repo->semester((int) $request->query('semester')) : null;
        $semester = $requestedSemester ?? $this->repo->semesterOn($today);
        // Another academic year chosen in the filter: switch to its first semester.
        $yearId = ctype_digit((string) $request->query('year', '')) ? (int) $request->query('year') : null;
        if ($yearId !== null && isset($yearOptions[$yearId]) && ($semester === null || (int) $semester['academic_year_id'] !== $yearId)) {
            $first = $this->repo->semestersOfYear($yearId)[0] ?? null;
            $semester = $first !== null ? $this->repo->semester((int) $first['id']) : $semester;
        }
        if ($semester === null) {
            $active = $years->active();
            $semester = $active !== null && $active['semesters'] !== [] ? $this->repo->semester((int) $active['semesters'][0]['id']) : null;
        }

        // Default view: own schedule for teachers and students.
        $mode = in_array($request->query('mode'), ['group', 'teacher', 'classroom'], true) ? (string) $request->query('mode') : null;
        $entityId = ctype_digit((string) $request->query('id', '')) ? (int) $request->query('id') : null;
        if ($mode === null) {
            $ownTeacher = $this->repo->teacherIdOfUser((int) Auth::id());
            $ownGroup = $this->repo->groupOfStudentUser((int) Auth::id());
            [$mode, $entityId] = match (true) {
                $ownGroup !== null => ['group', $ownGroup],
                $ownTeacher !== null && !Auth::can('schedule.manage') => ['teacher', $ownTeacher],
                default => ['group', null],
            };
        }

        $data = [
            'title' => __('nav.schedule'),
            'yearOptions' => $yearOptions,
            'semester' => $semester,
            'semesters' => $semester !== null ? $this->repo->semestersOfYear((int) $semester['academic_year_id']) : [],
            'mode' => $mode,
            'entityId' => $entityId,
            'options' => $this->options(),
            'canManage' => Auth::can('schedule.manage') && $semester !== null && $semester['year_status'] !== 'archived',
            'view' => $request->query('view') === 'day' ? 'day' : 'week',
            'periods' => $this->repo->periods(),
            'weekdays' => (array) Config::get('schedule.weekdays', [1, 2, 3, 4, 5, 6]),
        ];
        if ($semester === null) {
            return $this->view('schedules/index', $data + ['occurrences' => [], 'days' => [], 'week' => null]);
        }

        // Week: Monday of the requested date, kept inside the semester.
        $date = $this->date((string) $request->query('date', '')) ?? $today;
        $date = max((string) $semester['start_date'], min((string) $semester['end_date'], $date));
        $monday = (new DateTimeImmutable($date))->modify('-' . ((int) (new DateTimeImmutable($date))->format('N') - 1) . ' days');
        $days = [];
        foreach ($data['weekdays'] as $wd) {
            $days[$wd] = $monday->modify('+' . ($wd - 1) . ' days')->format('Y-m-d');
        }
        $filter = $entityId !== null ? [$mode . '_id' => $entityId] : ['group_id' => -1];
        [$from, $to] = $data['view'] === 'day' ? [$date, $date] : [reset($days), end($days)];

        return $this->view('schedules/index', $data + [
            'date' => $date,
            'days' => $days,
            'week' => [
                'monday' => $monday->format('Y-m-d'),
                'number' => ScheduleService::weekNumber((string) $semester['start_date'], $monday->format('Y-m-d')),
                'parity' => ScheduleService::weekParity((string) $semester['start_date'], $monday->format('Y-m-d')),
                'prev' => $monday->modify('-7 days')->format('Y-m-d'),
                'next' => $monday->modify('+7 days')->format('Y-m-d'),
            ],
            'occurrences' => (new ScheduleService($this->repo))->occurrences($semester, $from, $to, $filter),
            // Teachers on leave, sick leave or a business trip (HR data): substitutions are needed.
            'absentTeachers' => Auth::can('schedule.manage') ? (new HrService())->absentTeachers($from, $to) : [],
        ]);
    }

    public function changes(Request $request): Response
    {
        $filters = [
            'from' => $this->date((string) $request->query('from', '')) ?? '',
            'to' => $this->date((string) $request->query('to', '')) ?? '',
            'group' => ctype_digit((string) $request->query('group', '')) ? (int) $request->query('group') : null,
            'teacher' => ctype_digit((string) $request->query('teacher', '')) ? (int) $request->query('teacher') : null,
        ];

        return $this->view('schedules/changes', [
            'title' => __('schedule.changes_title'),
            'filters' => $filters,
            'options' => $this->options(),
            'result' => $this->repo->changesJournal($filters, max(1, (int) $request->query('page', 1))),
            'canManage' => Auth::can('schedule.manage'),
        ]);
    }

    /** POST /schedule/entries/{id}/changes — substitution, room change, cancellation or rescheduling. */
    public function storeChange(Request $request, int $id): Response
    {
        $entry = $this->repo->find($id) ?? throw new HttpException(404);
        $data = $this->validate($request, [
            'lesson_date' => 'required|date',
            'change_type' => 'required|in:modify,cancel,reschedule',
            'new_teacher_id' => 'nullable|integer|exists:teachers,id',
            'new_classroom_id' => 'nullable|integer|exists:classrooms,id',
            'new_date' => 'nullable|date',
            'new_lesson_period_id' => 'nullable|integer|exists:lesson_periods,id',
            'reason' => 'required|string|max:255',
        ]);
        if ($data['change_type'] === 'reschedule' && (empty($data['new_date']) || empty($data['new_lesson_period_id']))) {
            throw new ValidationException(['new_date' => [__('schedule.reschedule_needs_slot')]], $request->all());
        }
        $int = static fn (mixed $v): ?int => $v !== null && $v !== '' ? (int) $v : null;

        try {
            (new ScheduleService($this->repo))->changeOccurrence($entry, (string) $data['lesson_date'], [
                'change_type' => (string) $data['change_type'],
                'new_teacher_id' => $int($data['new_teacher_id'] ?? null),
                'new_classroom_id' => $int($data['new_classroom_id'] ?? null),
                'new_date' => $data['new_date'] ?? null,
                'new_lesson_period_id' => $int($data['new_lesson_period_id'] ?? null),
                'reason' => (string) $data['reason'],
            ]);
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $request->all());
        }
        Session::flash('success', __('schedule.change_saved'));

        return $this->back($request, '/schedule');
    }

    public function destroyChange(Request $request, int $id): Response
    {
        $change = $this->repo->findChange($id) ?? throw new HttpException(404);
        (new ScheduleService($this->repo))->revertChange($change);
        Session::flash('success', __('schedule.change_reverted'));

        return $this->back($request, '/schedule/changes');
    }

    /** @return array<string, array<int, string>> */
    private function options(): array
    {
        $map = static function (string $sql, callable $label): array {
            $options = [];
            foreach (Database::fetchAll($sql) as $row) {
                $options[(int) $row['id']] = $label($row);
            }

            return $options;
        };

        return [
            'groups' => $map("SELECT id, code FROM `groups` WHERE status = 'active' ORDER BY code", static fn (array $r): string => (string) $r['code']),
            'teachers' => $map(
                "SELECT t.id, u.last_name, u.first_name, u.middle_name FROM teachers t JOIN users u ON u.id = t.user_id
                 WHERE t.status <> 'dismissed' AND u.deleted_at IS NULL ORDER BY u.last_name, u.first_name",
                static fn (array $r): string => full_name($r, true)
            ),
            'classrooms' => $map(
                'SELECT id, number, building, capacity FROM classrooms WHERE is_active = 1 ORDER BY building, number',
                static fn (array $r): string => trim(($r['building'] !== '' ? $r['building'] . '-' : '') . $r['number']) . ' (' . $r['capacity'] . ')'
            ),
            'subjects' => $map(
                'SELECT id, code, name_ru, name_kk FROM subjects WHERE is_active = 1 ORDER BY code',
                static fn (array $r): string => $r['code'] . ' — ' . localized($r)
            ),
        ];
    }

    private function date(string $value): ?string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value ? $value : null;
    }
}
