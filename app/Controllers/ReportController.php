<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Csv;
use App\Repositories\ScheduleRepository;
use App\Services\JournalService;
use App\Services\Order130;
use App\Services\ReportService;

/**
 * Reports: the hub and two academic reports by group and semester — performance and attendance —
 * each viewable, exportable to Excel (CSV) and printable on A4 (PDF through the print dialog).
 * Workload reports live in the workload module and are linked from the hub.
 */
final class ReportController extends Controller
{
    private ReportService $reports;

    public function __construct()
    {
        $this->reports = new ReportService();
    }

    public function index(): Response
    {
        return $this->view('reports/index', ['title' => __('reports.title')]);
    }

    public function performance(Request $request): Response
    {
        return $this->report($request, 'performance');
    }

    public function attendance(Request $request): Response
    {
        return $this->report($request, 'attendance');
    }

    /** Summary grade sheet: five-point marks, absolute performance, quality and average by subject and group. */
    public function gradesheet(Request $request): Response
    {
        return $this->report($request, 'gradesheet');
    }

    private function report(Request $request, string $kind): Response
    {
        $groups = $this->reports->groups();
        $semesters = $this->semesters();
        $semesterId = (int) $request->query('semester', 0);
        if (!isset($semesters[$semesterId])) {
            $semesterId = (int) ((new ScheduleRepository())->semesterOn(date('Y-m-d'))['id'] ?? array_key_first($semesters) ?? 0);
        }
        $groupId = (int) $request->query('group', 0);
        if ($groupId !== 0 && !isset($groups[$groupId])) {
            throw new HttpException(403);
        }
        $groupId = $groupId !== 0 ? $groupId : (int) (array_key_first($groups) ?? 0);
        $empty = ['subjects' => [], 'students' => []];
        $data = $groupId !== 0 && $semesterId !== 0
            ? ($kind === 'gradesheet' ? $this->reports->gradeSheet($groupId, $semesterId) : $this->reports->build($groupId, $semesterId))
            : $empty;
        $title = __('reports.' . $kind) . ($groupId !== 0 ? ' · ' . $groups[$groupId] : '');
        $format = (string) $request->query('format', '');

        if ($format === 'csv' && $kind === 'gradesheet') {
            return Csv::download($this->gradeSheetRows($data, $groups[$groupId] ?? '', $semesters[$semesterId] ?? ''), $kind . '-' . ($groups[$groupId] ?? 'group') . '-' . date('Ymd') . '.csv');
        }

        if ($format === 'csv') {
            return Csv::download($this->rows($kind, $data, $groups[$groupId] ?? '', $semesters[$semesterId] ?? ''), $kind . '-' . ($groups[$groupId] ?? 'group') . '-' . date('Ymd') . '.csv');
        }

        if ($format === 'print' && $kind === 'gradesheet') {
            // Form 5.5 of Order No. 130 in the five-point system, with the group totals under it.
            $order = new Order130();
            $empty55 = ['group' => [], 'year' => '', 'subjects' => [], 'students' => []];

            return $this->view('reports/gradesheet_o130', [
                'title' => __('reports.gradesheet') . ($groupId !== 0 ? ' · ' . $groups[$groupId] : ''),
                'form' => $order->withTotals($groupId !== 0 && $semesterId !== 0 ? $order->yearResults($groupId, $semesterId) : $empty55),
            ], 'layouts/print');
        }

        if ($format === 'print') {
            // Form 5.5 of the theory journal, Order No. 130 (MES RK, 06.04.2020), for the whole academic year.
            return $this->view('reports/form55', [
                'title' => __('o130.f55_title') . ($groupId !== 0 ? ' · ' . $groups[$groupId] : ''),
                'form' => $groupId !== 0 && $semesterId !== 0 ? (new Order130())->yearResults($groupId, $semesterId) : ['group' => [], 'year' => '', 'subjects' => [], 'students' => []],
                'part' => $kind === 'performance' ? 'results' : 'missed',
            ], 'layouts/print');
        }

        return $this->view('reports/' . $kind, [
            'title' => $title,
            'kind' => $kind,
            'data' => $data,
            'groups' => $groups,
            'semesters' => $semesters,
            'groupId' => $groupId,
            'semesterId' => $semesterId,
            'groupCode' => $groups[$groupId] ?? '',
            'semesterTitle' => $semesters[$semesterId] ?? '',
            'print' => $format === 'print',
        ], $format === 'print' ? 'layouts/print' : 'layouts/app');
    }

    /** @return list<list<scalar|null>> */
    private function rows(string $kind, array $data, string $group, string $semester): array
    {
        $n = static fn ($v): string => JournalService::normalize($v) ?? '';
        $rows = [[__('reports.' . $kind), $group, $semester]];
        $header = ['№', __('journal.student'), __('fields.student_number')];
        foreach ($data['subjects'] as $s) {
            $header[] = trim($s['code'] . ' ' . $s['name']);
        }
        array_push($header, ...($kind === 'performance' ? [__('reports.debts')] : [__('reports.absent'), __('reports.excused'), __('reports.attended')]));
        $rows[] = $header;
        foreach ($data['students'] as $i => $st) {
            $row = [$i + 1, $st['name'], $st['number']];
            foreach ($data['subjects'] as $s) {
                $c = $st['cells'][$s['id']] ?? null;
                $row[] = $c === null ? '' : ($kind === 'performance' ? $n($c['final']) : $c['absent'] . ' / ' . $c['excused']);
            }
            if ($kind === 'performance') {
                $row[] = $st['debts'];
            } else {
                $pct = ReportService::attendance($st['lessons'], $st['absent'], $st['excused']);
                array_push($row, $st['absent'], $st['excused'], $pct === null ? '' : $pct . '%');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return list<list<scalar|null>> */
    private function gradeSheetRows(array $data, string $group, string $semester): array
    {
        $pct = static fn (?float $v): string => $v === null ? '' : $v . '%';
        $num = static fn (?float $v): string => $v === null ? '' : (string) $v;
        $rows = [[__('reports.gradesheet'), $group, $semester]];
        $header = ['№', __('journal.student')];
        foreach ($data['subjects'] as $s) {
            $header[] = trim($s['code'] . ' ' . $s['name']);
        }
        $header[] = __('reports.gs_average');
        $rows[] = $header;
        foreach ($data['students'] as $i => $st) {
            $row = [$i + 1, $st['name']];
            foreach ($data['subjects'] as $s) {
                $g = $st['cells'][$s['id']]['grade'] ?? null;
                $row[] = $g === null ? '' : $g['mark'] . ($g['letter'] !== null ? ' (' . $g['letter'] . ')' : '');
            }
            $row[] = $num($st['stats']['average']);
            $rows[] = $row;
        }
        foreach (['absolute' => $pct, 'quality' => $pct, 'average' => $num] as $key => $fmt) {
            $row = ['', __('reports.gs_' . $key)];
            foreach ($data['subjects'] as $s) {
                $row[] = $fmt($data['subject_stats'][$s['id']][$key] ?? null);
            }
            $row[] = $fmt($data['summary'][$key] ?? null);
            $rows[] = $row;
        }
        if (isset($data['summary'])) {
            $rows[] = [];
            $rows[] = [__('reports.gs_group_total')];
            $rows[] = [__('reports.gs_students'), $data['summary']['students']];
            foreach ($data['summary']['categories'] as $key => $names) {
                $rows[] = [__('reports.gs_cat.' . $key), count($names), implode(', ', $names)];
            }
            $rows[] = [__('reports.gs_absolute'), $pct($data['summary']['absolute'])];
            $rows[] = [__('reports.gs_quality'), $pct($data['summary']['quality'])];
            $rows[] = [__('reports.gs_average'), $num($data['summary']['average'])];
        }

        return $rows;
    }

    /** @return array<int, string> semesters of current and recent years */
    private function semesters(): array
    {
        $out = [];
        foreach (Database::fetchAll(
            "SELECT s.id, s.number, y.title FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id
             WHERE y.status <> 'planned' OR y.start_date <= CURDATE() ORDER BY s.start_date DESC LIMIT 12"
        ) as $s) {
            $out[(int) $s['id']] = str_replace('-', '–', (string) $s['title']) . ', ' . __('dashboard.semester', ['number' => $s['number']]);
        }

        return $out;
    }
}
