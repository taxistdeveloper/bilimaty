<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Repositories\AcademicYearRepository;
use App\Repositories\CalendarRepository;
use App\Repositories\OptionsRepository;
use App\Services\AccessScope;
use App\Services\AuditLogger;
use App\Services\CalendarGrid;
use DateTimeImmutable;

/**
 * Academic calendar. View: calendar.view (a student sees only events of their own
 * group/course); changes: calendar.manage, only in non-archived years.
 */
final class CalendarController extends Controller
{
    public const TYPES = ['theory', 'exam_session', 'practice', 'holiday', 'public_holiday', 'final_attestation', 'control_week', 'other'];

    private CalendarRepository $events;
    private AcademicYearRepository $years;

    public function __construct()
    {
        $this->events = new CalendarRepository();
        $this->years = new AcademicYearRepository();
    }

    public function index(Request $request): Response
    {
        $context = $this->context($request);
        $year = $context['year'];
        if ($year === null) {
            return $this->view('calendars/index', ['title' => __('nav.calendar'), 'year' => null] + $context);
        }

        $months = CalendarGrid::months($year['start_date'], $year['end_date']);
        $month = (string) $request->query('month', '');
        if (!in_array($month, $months, true)) {
            $month = in_array(date('Y-m'), $months, true) ? date('Y-m') : $months[0];
        }
        $index = array_search($month, $months, true);
        $events = $this->events->events((int) $year['id'], $context['audience']);

        return $this->view('calendars/index', [
            'title' => __('nav.calendar'),
            'month' => $month,
            'prevMonth' => $months[$index - 1] ?? null,
            'nextMonth' => $months[$index + 1] ?? null,
            'months' => $months,
            'weeks' => CalendarGrid::month($month, $events, $year['semesters']),
            'events' => $events,
            'tab' => $request->query('tab') === 'list' ? 'list' : 'month',
        ] + $context);
    }

    /** Print-ready A4 page; the browser saves it as PDF (Print → Save as PDF). */
    public function print(Request $request): Response
    {
        $context = $this->context($request);
        if ($context['year'] === null) {
            throw new HttpException(404);
        }

        return $this->view('calendars/print', [
            'title' => __('calendar.print_title') . ' ' . $context['year']['title'],
            'events' => $this->events->events((int) $context['year']['id'], $context['audience']),
        ] + $context, 'layouts/print');
    }

    public function create(Request $request): Response
    {
        $year = $this->editableYear((int) $request->query('year', 0));
        $date = (string) $request->query('date', '');
        $date = $this->isDate($date) && $date >= $year['start_date'] && $date <= $year['end_date'] ? $date : $year['start_date'];

        return $this->form($year, null, ['type' => 'theory', 'start_date' => $date, 'end_date' => $date]);
    }

    public function store(Request $request): Response
    {
        $year = $this->editableYear((int) $request->input('academic_year_id', 0));
        $data = $this->validated($request, $year);
        $id = $this->events->create($data + ['academic_year_id' => (int) $year['id'], 'created_by' => Auth::id()]);
        AuditLogger::log('calendar.created', 'academic_calendar_events', $id, null, $data);
        Session::flash('success', __('calendar.created'));

        return $this->redirect('/calendar?year=' . $year['id'] . '&month=' . substr($data['start_date'], 0, 7));
    }

    public function edit(int $id): Response
    {
        $event = $this->events->find($id) ?? throw new HttpException(404);
        $year = $this->editableYear((int) $event['academic_year_id']);

        return $this->form($year, $event, $event);
    }

    public function update(Request $request, int $id): Response
    {
        $event = $this->events->find($id) ?? throw new HttpException(404);
        $year = $this->editableYear((int) $event['academic_year_id']);
        $data = $this->validated($request, $year);
        $this->events->update($id, $data);
        AuditLogger::log('calendar.updated', 'academic_calendar_events', $id, array_intersect_key($event, $data), $data);
        Session::flash('success', __('calendar.updated'));

        return $this->redirect('/calendar?year=' . $year['id'] . '&month=' . substr($data['start_date'], 0, 7));
    }

    public function destroy(int $id): Response
    {
        $event = $this->events->find($id) ?? throw new HttpException(404);
        $year = $this->editableYear((int) $event['academic_year_id']);
        $this->events->delete($id);
        AuditLogger::log('calendar.deleted', 'academic_calendar_events', $id, array_intersect_key($event, array_flip(['type', 'title_ru', 'start_date', 'end_date'])));
        Session::flash('success', __('calendar.deleted'));

        return $this->redirect('/calendar?year=' . $year['id'] . '&month=' . substr((string) $event['start_date'], 0, 7));
    }

    /** Add fixed-date public holidays (config/holidays.php) that fall inside the year. */
    public function holidays(int $yearId): Response
    {
        $year = $this->editableYear($yearId);
        $added = 0;
        $startYear = (int) substr((string) $year['start_date'], 0, 4);
        foreach ((array) Config::get('holidays', []) as $monthDay => [$ru, $kk, $days]) {
            foreach ([$startYear, $startYear + 1] as $y) {
                $start = $y . '-' . $monthDay;
                if ($start < $year['start_date'] || $start > $year['end_date'] || $this->events->existsOn((int) $year['id'], 'public_holiday', $start)) {
                    continue;
                }
                $end = (new DateTimeImmutable($start))->modify('+' . ((int) $days - 1) . ' days')->format('Y-m-d');
                $this->events->create([
                    'academic_year_id' => (int) $year['id'], 'type' => 'public_holiday', 'title_ru' => $ru, 'title_kk' => $kk,
                    'start_date' => $start, 'end_date' => min($end, (string) $year['end_date']), 'course' => null, 'group_id' => null,
                    'note' => null, 'created_by' => Auth::id(),
                ]);
                $added++;
            }
        }
        AuditLogger::log('calendar.holidays_added', 'academic_years', (int) $year['id'], null, ['added' => $added]);
        Session::flash('success', __('calendar.holidays_added', ['count' => $added]));

        return $this->redirect('/calendar?year=' . $year['id'] . '&tab=list');
    }

    /**
     * Year, audience and filter options for the current user.
     *
     * @return array<string, mixed>
     */
    private function context(Request $request): array
    {
        $yearOptions = [];
        foreach ($this->years->allWithStats() as $y) {
            $yearOptions[(int) $y['id']] = str_replace('-', '–', (string) $y['title']) . ($y['status'] === 'active' ? ' ★' : '');
        }
        $yearId = (int) $request->query('year', 0);
        $year = $yearId > 0 && isset($yearOptions[$yearId]) ? $this->years->find($yearId) : $this->years->active();

        // Students see only their own group's calendar; staff can filter by group or course.
        $placement = !Auth::can('groups.view') ? $this->events->studentPlacement((int) Auth::id()) : null;
        $groups = $placement === null ? (new OptionsRepository())->groups() : [];
        $groupId = ctype_digit((string) $request->query('group', '')) ? (int) $request->query('group') : null;
        if ($groupId !== null && !isset($groups[$groupId])) {
            $groupId = null;
        }
        $course = ctype_digit((string) $request->query('course', '')) && (int) $request->query('course') >= 1 && (int) $request->query('course') <= 4
            ? (int) $request->query('course') : null;
        $type = in_array($request->query('type'), self::TYPES, true) ? (string) $request->query('type') : '';

        $audience = $placement !== null
            ? ['group_id' => (int) $placement['group_id'], 'type' => $type]
            : ['group_id' => $groupId, 'course' => $groupId === null ? $course : null, 'type' => $type];

        return [
            'year' => $year,
            'yearOptions' => $yearOptions,
            'groups' => $groups,
            'placement' => $placement,
            'filters' => ['year' => $year['id'] ?? null, 'group' => $groupId, 'course' => $course, 'type' => $type],
            'audience' => $audience,
            'canManage' => Auth::can('calendar.manage') && $year !== null && $year['status'] !== 'archived',
            'types' => self::TYPES,
        ];
    }

    /**
     * @param array<string, mixed> $year
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $year): array
    {
        $data = $this->validate($request, [
            'type' => 'required|in:' . implode(',', self::TYPES),
            'title_ru' => 'required|string|max:255',
            'title_kk' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'course' => 'nullable|integer|between:1,4',
            'group_id' => 'nullable|integer|exists:groups,id',
            'note' => 'nullable|string|max:500',
        ]);

        $errors = [];
        if ($data['end_date'] < $data['start_date']) {
            $errors['end_date'][] = __('years.end_after_start');
        }
        if ($data['start_date'] < $year['start_date'] || $data['end_date'] > $year['end_date']) {
            $errors['start_date'][] = __('calendar.outside_year', ['from' => format_date($year['start_date']), 'to' => format_date($year['end_date'])]);
        }
        if (!empty($data['course']) && !empty($data['group_id'])) {
            $errors['group_id'][] = __('calendar.course_or_group');
        }
        if (!empty($data['group_id']) && !AccessScope::allowsGroup((int) $data['group_id'])) {
            $errors['group_id'][] = __('students.group_not_allowed');
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $request->all());
        }

        return [
            'type' => (string) $data['type'],
            'title_ru' => (string) $data['title_ru'],
            'title_kk' => (string) $data['title_kk'],
            'start_date' => (string) $data['start_date'],
            'end_date' => (string) $data['end_date'],
            'course' => !empty($data['course']) ? (int) $data['course'] : null,
            'group_id' => !empty($data['group_id']) ? (int) $data['group_id'] : null,
            'note' => $data['note'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $year
     * @param array<string, mixed>|null $event
     * @param array<string, mixed> $values
     */
    private function form(array $year, ?array $event, array $values): Response
    {
        return $this->view('calendars/form', [
            'title' => $event === null ? __('calendar.create') : __('calendar.edit'),
            'year' => $year,
            'event' => $event,
            'values' => $values,
            'types' => self::TYPES,
            'groups' => (new OptionsRepository())->groups(),
        ]);
    }

    /** @return array<string, mixed> */
    private function editableYear(int $id): array
    {
        if ($id === 0) {
            // Without an explicit year the current (active) academic year is used.
            $id = (int) (\App\Core\Database::fetchColumn("SELECT id FROM academic_years WHERE status = 'active' LIMIT 1") ?? 0);
        }
        $year = $this->years->find($id) ?? throw new HttpException(404);
        if ($year['status'] === 'archived') {
            throw new HttpException(403, __('years.archived_readonly'));
        }

        return $year;
    }

    private function isDate(string $value): bool
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }
}
