<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Repositories\ExamRepository;
use App\Repositories\JournalRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\StudentRepository;
use DateTimeImmutable;

/**
 * Personal assistant: understands short questions in Russian or Kazakh (typed or
 * recognised from voice) and answers from the user's own data — timetable, next
 * lesson, upcoming exams, grades. Rule-based, works offline, no external AI service.
 *
 * Answer: {text, speech, sections: [{title, rows: [{time, title, meta, off}]}], link: ?{url, label}}.
 * "text" is the short reply shown in the chat, "speech" the full one read aloud.
 */
final class AssistantService
{
    private const OFF = ['cancelled', 'moved_out', 'suspended'];

    private readonly DateTimeImmutable $now;

    public function __construct(
        private readonly ScheduleRepository $schedule = new ScheduleRepository(),
        ?DateTimeImmutable $now = null,
    ) {
        $this->now = $now ?? new DateTimeImmutable();
    }

    // ------------------------------------------------------------------ understanding

    /**
     * What the question asks for.
     *
     * @return array{intent: string, date?: string, from?: string, to?: string}
     */
    public function parse(string $text): array
    {
        $t = ' ' . str_replace('ё', 'е', mb_strtolower(trim($text))) . ' ';
        $has = static fn (string $pattern): bool => preg_match('/' . $pattern . '/u', $t) === 1;

        if ($has('помо[гщ]|что (ты )?умеешь|команд|көмек|не істей|не біле')) {
            return ['intent' => 'help'];
        }
        if ($has('экзам|емтихан|зач[её]т|сынақ')) {
            return ['intent' => 'exams'];
        }
        if ($has('оценк|балл|успеваем|пропуск|баға|бағал|үлгерім')) {
            return ['intent' => 'grades'];
        }
        if ($has('котор\S* час|сколько времени|сағат неше|қазір неше')) {
            return ['intent' => 'time'];
        }
        if ($has('(?<!\\p{L})(недел|апта)')) {
            $monday = $this->today()->modify('monday this week');
            if ($has('следующ|будущ|келесі|алдағы')) {
                $monday = $monday->modify('+7 days');
            }

            return ['intent' => 'week', 'from' => $monday->format('Y-m-d'), 'to' => $monday->modify('+6 days')->format('Y-m-d')];
        }

        $date = $this->dateIn($t);
        if ($date === null && $has('следующ|ближайш|сейчас|келесі|қазір')) {
            return ['intent' => 'next'];
        }
        if ($date !== null) {
            return ['intent' => 'day', 'date' => $date];
        }
        if ($has('пар[аыуе]?(?!\p{L})|урок|заняти|распис|сабақ|кесте')) {
            return ['intent' => 'day', 'date' => $this->today()->format('Y-m-d')];
        }
        if ($has('привет|здравствуй|добр\S+ (утро|день|вечер)|сәлем|салем|қайырлы')) {
            return ['intent' => 'hello'];
        }

        return ['intent' => 'unknown'];
    }

    /** A day named in the question: relative words first, then a weekday (the nearest one, today included). */
    private function dateIn(string $t): ?string
    {
        $relative = [
            'послезавтра|бүрсігүні|бүрсүгүні|арғы күні' => 2,
            'завтра|ертең|ертен' => 1,
            'сегодня|бүгін|бугин' => 0,
            'вчера|кеше' => -1,
        ];
        foreach ($relative as $pattern => $shift) {
            if (preg_match('/(?<!\p{L})(' . $pattern . ')/u', $t) === 1) {
                return $this->today()->modify(sprintf('%+d days', $shift))->format('Y-m-d');
            }
        }

        $weekdays = [
            1 => 'понедельник|дүйсенбі',
            2 => 'вторник|сейсенбі',
            3 => 'сред[ауы](?!\p{L})|сәрсенбі',
            4 => 'четверг|бейсенбі',
            5 => 'пятниц|жұма',
            6 => 'суббот|сенбі',
            7 => 'воскресен|жексенбі',
        ];
        foreach ($weekdays as $n => $pattern) {
            if (preg_match('/(?<!\p{L})(' . $pattern . ')/u', $t) === 1) {
                $shift = ($n - (int) $this->today()->format('N') + 7) % 7;

                return $this->today()->modify('+' . $shift . ' days')->format('Y-m-d');
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ answering

    /** @return array{text: string, sections: list<array<string, mixed>>, link: ?array{url: string, label: string}} */
    public function answer(string $question, int $userId): array
    {
        $q = $this->parse($question);

        return match ($q['intent']) {
            'help' => $this->reply(__('assistant.help')),
            'hello' => $this->reply(__('assistant.hello', ['name' => (string) (Auth::user()['first_name'] ?? '')])),
            'time' => $this->reply(__('assistant.time_now', ['time' => $this->now->format('H:i'), 'date' => $this->dayLabel($this->today()->format('Y-m-d'))])),
            'day' => $this->day($userId, (string) $q['date']),
            'week' => $this->week($userId, (string) $q['from'], (string) $q['to']),
            'next' => $this->next($userId),
            'exams' => $this->exams($userId),
            'grades' => $this->grades($userId),
            default => $this->reply(__('assistant.not_understood')),
        };
    }

    private function day(int $userId, string $date): array
    {
        $who = $this->who($userId);
        if (is_array($who) && isset($who['text'])) {
            return $who;
        }
        $label = $this->dayLabel($date);
        $lead = $label . (str_contains($label, ',') ? ',' : '');
        $semester = $this->schedule->semesterOn($date);
        if ($semester === null) {
            return $this->reply(__('assistant.no_semester', ['day' => $lead]));
        }
        $items = (new ScheduleService($this->schedule))->occurrences($semester, $date, $date, $who['filter']);
        $active = array_values(array_filter($items, static fn (array $o): bool => !in_array($o['status'], self::OFF, true)));
        $link = $this->link('/schedule?view=day&date=' . $date, __('assistant.open_schedule'));
        if ($items === []) {
            return $this->reply(__('assistant.day_free', ['day' => $lead]), [], $link);
        }

        $sentences = [$active === []
            ? __('assistant.day_all_off', ['day' => $lead])
            : __('assistant.day_summary', [
                'day' => $lead,
                'count' => $this->lessonsCount($this->periods($active)),
                'start' => $this->time($active[0]['start_time']),
                'end' => $this->time($active[count($active) - 1]['end_time']),
            ])];
        return $this->reply(
            $sentences[0],
            [['title' => self::ucfirst($label), 'rows' => array_map(fn (array $o): array => $this->lessonRow($o, $who['mode']), $items)]],
            $link,
            $active === [] ? $this->sayDay($date, $items, $active, $who['mode']) : $this->human($this->sayDay($date, $items, $active, $who['mode']), 'day')
        );
    }

    private function week(int $userId, string $from, string $to): array
    {
        $who = $this->who($userId);
        if (is_array($who) && isset($who['text'])) {
            return $who;
        }
        $semester = $this->schedule->semesterOn($from) ?? $this->schedule->semesterOn($to);
        $items = $semester === null ? [] : (new ScheduleService($this->schedule))->occurrences($semester, $from, $to, $who['filter']);
        $link = $this->link('/schedule?view=week&date=' . $from, __('assistant.open_schedule'));
        if ($items === []) {
            return $this->reply(__('assistant.week_free'), [], $link);
        }

        $byDate = [];
        foreach ($items as $o) {
            $byDate[$o['date']][] = $o;
        }
        $total = 0;
        $sentences = [];
        $sections = [];
        foreach ($byDate as $date => $list) {
            $active = array_values(array_filter($list, static fn (array $o): bool => !in_array($o['status'], self::OFF, true)));
            $total += $this->periods($active);
            $title = self::ucfirst($this->weekdayDate($date));
            $sentences[] = $active === []
                ? __('assistant.say.week_day_off', ['day' => self::ucfirst($this->sayWeekday($date))])
                : __('assistant.say.week_day', ['day' => self::ucfirst($this->sayWeekday($date)), 'count' => $this->sayCount($this->periods($active)), 'start' => $this->sayTime($active[0]['start_time']), 'end' => $this->sayTime($active[count($active) - 1]['end_time'])]);
            $sections[] = ['title' => $title, 'rows' => array_map(fn (array $o): array => $this->lessonRow($o, $who['mode']), $list)];
        }
        $text = __('assistant.week_summary', ['count' => $this->lessonsCount($total)]);
        array_unshift($sentences, __('assistant.say.week_summary', ['count' => $this->sayCount($total)]));

        return $this->reply($text, $sections, $link, $this->human(implode(' ', $sentences), 'week'));
    }

    private function next(int $userId): array
    {
        $who = $this->who($userId);
        if (is_array($who) && isset($who['text'])) {
            return $who;
        }
        $today = $this->today()->format('Y-m-d');
        $until = $this->today()->modify('+14 days')->format('Y-m-d');
        $semester = $this->schedule->semesterOn($today) ?? $this->schedule->semesterOn($until);
        $items = $semester === null ? [] : (new ScheduleService($this->schedule))->occurrences($semester, $today, $until, $who['filter']);
        $now = $this->now->format('H:i:s');

        foreach ($items as $o) {
            if (in_array($o['status'], self::OFF, true) || ($o['date'] === $today && (string) $o['end_time'] <= $now)) {
                continue;
            }
            $current = $o['date'] === $today && (string) $o['start_time'] <= $now;
            $text = __($current ? 'assistant.now_lesson' : 'assistant.next_lesson', [
                'n' => (int) $o['period_number'],
                'subject' => $this->subject($o),
                'day' => self::lcfirst($this->dayLabel((string) $o['date'])),
                'start' => $this->time($o['start_time']),
                'end' => $this->time($o['end_time']),
                'where' => $this->where($o, $who['mode']),
            ]);

            $speech = __($current ? 'assistant.say.now' : 'assistant.say.next', [
                'nth' => $this->sayOrdinal((int) $o['period_number']),
                'subject' => $this->subject($o),
                'day' => $this->sayDayName((string) $o['date']),
                'start' => $this->sayTime($o['start_time']),
                'end' => $this->sayTime($o['end_time']),
                'where' => $this->sayWhere([$o], $who['mode']),
            ]);

            return $this->reply(
                $text,
                [['title' => self::ucfirst($this->dayLabel((string) $o['date'])), 'rows' => [$this->lessonRow($o, $who['mode'])]]],
                $this->link('/schedule?view=day&date=' . $o['date'], __('assistant.open_schedule')),
                $this->human($speech, $current ? 'now' : 'next', false)
            );
        }

        return $this->reply(__('assistant.next_none'));
    }

    private function exams(int $userId): array
    {
        if (!Auth::can('exams.view')) {
            return $this->reply(__('assistant.no_access'));
        }
        $repo = new ExamRepository();
        $today = $this->today()->format('Y-m-d');
        $studentId = (new JournalRepository())->studentIdOfUser($userId);
        if ($studentId !== null) {
            $list = $repo->forStudent($studentId, $today);
        } elseif (($teacherId = $this->schedule->teacherIdOfUser($userId)) !== null) {
            $list = $repo->upcomingForTeacher($teacherId, $today, 5);
        } else {
            return $this->reply(__('assistant.no_profile'));
        }
        $list = array_slice($list, 0, 5);
        $link = $this->link($studentId !== null ? '/exams/my' : '/exams', __('assistant.open_exams'));
        if ($list === []) {
            return $this->reply(__('assistant.exams_none'), [], $link);
        }

        $sentences = [];
        $later = [];
        $rows = [];
        foreach ($list as $e) {
            $type = localized(['name_ru' => $e['type_name_ru'], 'name_kk' => $e['type_name_kk']]);
            $subject = localized(['name_ru' => $e['subject_name_ru'], 'name_kk' => $e['subject_name_kk']]);
            $room = $e['classroom_number'] !== null ? trim(((string) $e['classroom_building'] !== '' ? $e['classroom_building'] . '-' : '') . $e['classroom_number']) : null;
            $start = $e['start_time'] !== null ? $this->time($e['start_time']) : '';
            if ($sentences === []) {
                $sentences[] = __('assistant.say.exam_first', [
                    'type' => mb_strtolower($type),
                    'subject' => $subject,
                    'day' => $this->sayDayName((string) $e['exam_date'], true),
                    'start' => $e['start_time'] !== null ? $this->sayTime($e['start_time']) : '',
                    'where' => $room !== null ? ', ' . __('assistant.room_short', ['room' => $room]) : '',
                ]);
            } else {
                $later[] = __('assistant.say.exam_later', ['subject' => $subject, 'date' => $this->sayDate((string) $e['exam_date'])]);
            }
            $rows[] = [
                'time' => format_date((string) $e['exam_date'], 'd.m') . ($start !== '' ? ' · ' . $start : ''),
                'title' => $subject,
                'meta' => $type . ' · ' . ($studentId === null ? $e['group_code'] . ' · ' : '') . __('schedule.room') . ' ' . ($room ?? '—'),
                'off' => false,
            ];
        }

        if ($later !== []) {
            $sentences[] = __('assistant.say.exam_more', ['list' => implode(', ', $later)]);
        }

        return $this->reply(__('assistant.exams_count', ['n' => count($rows)]), [['title' => __('assistant.exams_title'), 'rows' => $rows]], $link, $this->human(implode(' ', $sentences), 'exams'));
    }

    private function grades(int $userId): array
    {
        $journals = new JournalRepository();
        $studentId = Auth::can('journal.view') ? $journals->studentIdOfUser($userId) : null;
        if ($studentId === null) {
            return $this->reply(__('assistant.grades_only_students'));
        }
        $semester = $this->schedule->semesterOn($this->today()->format('Y-m-d'));
        $service = new JournalService($journals);
        $rows = [];
        $parts = [];
        $absent = 0;
        foreach ($journals->journalsOfStudent($studentId) as $j) {
            if ($semester !== null && (int) $j['semester_id'] !== (int) $semester['id']) {
                continue;
            }
            $r = $service->studentResult($j, $studentId);
            $miss = (int) ($r['absent'] ?? 0) + (int) ($r['excused'] ?? 0);
            $absent += $miss;
            $final = $j['status'] === 'approved' ? ($r['fixed_final'] ?? $r['final'] ?? null) : ($r['final'] ?? null);
            $subject = localized(['name_ru' => $j['subject_name_ru'], 'name_kk' => $j['subject_name_kk']]);
            $value = $final !== null ? (string) $final : '—';
            if ($final !== null) {
                $parts[] = __('assistant.grade_line', ['subject' => $subject, 'value' => $value]);
            }
            $rows[] = ['time' => $value, 'title' => $subject, 'meta' => __('assistant.absences_short', ['n' => $miss]), 'off' => false];
        }
        $link = $this->link('/', __('assistant.open_dashboard'));
        if ($rows === []) {
            return $this->reply(__('assistant.grades_none'), [], $link);
        }
        $text = ($parts === [] ? __('assistant.grades_empty') : __('assistant.grades_summary') . ' ' . implode('; ', $parts) . '.')
            . ' ' . __('assistant.absences', ['n' => $absent]);

        return $this->reply($text, [['title' => __('assistant.grades_title'), 'rows' => $rows]], $link);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Timetable owner: the student's group or the teacher, or a ready refusal.
     *
     * @return array{mode: string, filter: array<string, int>}|array{text: string, sections: list<mixed>, link: null}
     */
    private function who(int $userId): array
    {
        if (!Auth::can('schedule.view')) {
            return $this->reply(__('assistant.no_access'));
        }
        $groupId = $this->schedule->groupOfStudentUser($userId);
        if ($groupId !== null) {
            return ['mode' => 'group', 'filter' => ['group_id' => $groupId]];
        }
        $teacherId = $this->schedule->teacherIdOfUser($userId);
        if ($teacherId !== null) {
            return ['mode' => 'teacher', 'filter' => ['teacher_id' => $teacherId]];
        }

        return $this->reply(__('assistant.no_profile'));
    }

    // ------------------------------------------------------------------ speech (natural, for the voice)

    /**
     * Sounds like a person, not a printout: an opening phrase (sometimes with the user's
     * name) and a friendly closing one, picked at random from a few variants.
     */
    private function human(string $speech, string $kind, bool $opener = true): string
    {
        $name = trim((string) (Auth::user()['first_name'] ?? ''));
        if (locale() !== 'kk') { // the Russian voice does not know Kazakh letters
            $name = strtr($name, ['І' => 'И', 'і' => 'и', 'Қ' => 'К', 'қ' => 'к', 'Ғ' => 'Г', 'ғ' => 'г', 'Ң' => 'Н', 'ң' => 'н',
                'Ә' => 'А', 'ә' => 'а', 'Ө' => 'О', 'ө' => 'о', 'Ұ' => 'У', 'ұ' => 'у', 'Ү' => 'У', 'ү' => 'у', 'Һ' => 'Х', 'һ' => 'х']);
        }
        $pick = static function (string $key) use ($name): string {
            $variants = array_values(array_filter(
                explode('|', __($key)),
                static fn (string $v): bool => !str_starts_with($v, 'assistant.') && ($name !== '' || !str_contains($v, ':name'))
            ));

            return $variants === [] ? '' : str_replace(':name', $name, $variants[random_int(0, count($variants) - 1)]);
        };
        $open = $opener ? $pick('assistant.say.open') : '';
        $close = $pick('assistant.say.close_' . $kind); // an empty variant = no closing phrase this time

        return trim($open . ' ' . $speech . ' ' . $close);
    }

    /** "Завтра, во вторник, у вас две пары. Начинаете в 8:30… Первая — информатика, кабинет 201. …" */
    private function sayDay(string $date, array $items, array $active, string $mode): string
    {
        $day = self::ucfirst($this->sayDayName($date));
        $day .= str_contains($day, ',') ? ',' : '';
        if ($active === []) {
            return __('assistant.say.day_all_off', ['day' => $day]);
        }
        $out = [__('assistant.say.day_summary', [
            'day' => $day,
            'count' => $this->sayCount($this->periods($active)),
            'start' => $this->sayTime($active[0]['start_time']),
            'end' => $this->sayTime($active[count($active) - 1]['end_time']),
        ])];

        $byPeriod = [];
        foreach ($items as $o) {
            $byPeriod[(int) $o['period_number']][] = $o;
        }
        foreach ($byPeriod as $n => $list) {
            $on = array_values(array_filter($list, static fn (array $o): bool => !in_array($o['status'], self::OFF, true)));
            $nth = self::ucfirst($this->sayOrdinal($n));
            if ($on === []) {
                $out[] = __('assistant.say.lesson_off', ['nth' => $nth, 'subject' => $this->subject($list[0]), 'status' => __('assistant.status_' . $list[0]['status'])]);
                continue;
            }
            $subjects = array_values(array_unique(array_map(fn (array $o): string => $this->subject($o), $on)));
            $out[] = __('assistant.say.lesson', [
                'nth' => $nth,
                'subject' => count($subjects) === 1 ? $subjects[0] : implode(' ' . __('assistant.say.or') . ' ', $subjects),
                'where' => $this->sayWhere($on, $mode),
            ]);
        }

        return implode(' ', $out);
    }

    /** ", кабинет 201" / ", по подгруппам: кабинеты 201 и 202" / " у группы ВТ-25-1, кабинет 210". */
    private function sayWhere(array $lessons, string $mode): string
    {
        $rooms = array_values(array_unique(array_filter(
            array_map(static fn (array $o): string => (string) $o['effective_classroom_label'], $lessons),
            static fn (string $r): bool => $r !== '—'
        )));
        $out = '';
        if ($mode === 'teacher') {
            $out .= ' ' . __('assistant.say.for_group', ['group' => $lessons[0]['group_code']]);
        } elseif (count($lessons) > 1) {
            $out .= ', ' . __('assistant.say.by_subgroups');
        }
        if (count($rooms) === 1) {
            $out .= ', ' . __('assistant.room_short', ['room' => $rooms[0]]);
        } elseif ($rooms !== []) {
            $last = array_pop($rooms);
            $out .= ', ' . __('assistant.say.rooms', ['list' => implode(', ', $rooms) . ' ' . __('assistant.say.and') . ' ' . $last]);
        }

        return $out;
    }

    /** "завтра, во вторник" / "сегодня" / "в пятницу" / "в понедельник, 21 декабря". */
    private function sayDayName(string $date, bool $withDate = false): string
    {
        $diff = (int) $this->today()->diff(new DateTimeImmutable($date))->format('%r%a');
        if ($diff === 0) {
            return mb_strtolower(__('assistant.today'));
        }
        if ($diff === 1 || $diff === 2) {
            return mb_strtolower(__('assistant.' . ($diff === 1 ? 'tomorrow' : 'day_after'))) . ', ' . $this->sayWeekday($date);
        }

        return $this->sayWeekday($date) . ($withDate || $diff < 0 || $diff > 6 ? ', ' . $this->sayDate($date) : '');
    }

    private function sayWeekday(string $date): string
    {
        return __('assistant.say.on_weekday.' . (new DateTimeImmutable($date))->format('N'));
    }

    private function sayDate(string $date): string
    {
        $d = new DateTimeImmutable($date);

        return __('assistant.say.date', ['d' => (int) $d->format('j'), 'month' => __('assistant.months.' . $d->format('n'))]);
    }

    /** 08:30 → "8:30", 13:00 → "13 ровно". */
    private function sayTime(mixed $value): string
    {
        [$h, $m] = array_map('intval', explode(':', (string) $value)) + [0, 0];

        return $m === 0 ? __('assistant.say.time_whole', ['h' => $h]) : $h . ':' . sprintf('%02d', $m);
    }

    /** 2 → "две пары". */
    private function sayCount(int $n): string
    {
        $word = __('assistant.say.numbers.' . $n);

        return (str_starts_with($word, 'assistant.') ? (string) $n : $word) . ' ' . mb_substr($this->lessonsCount($n), mb_strlen((string) $n) + 1);
    }

    /** 2 → "вторая". */
    private function sayOrdinal(int $n): string
    {
        $word = __('assistant.say.ordinals.' . $n);

        return str_starts_with($word, 'assistant.') ? (string) $n : $word;
    }

    /** @return array{time: string, title: string, meta: string, off: bool} */
    private function lessonRow(array $o, string $mode): array
    {
        $off = in_array($o['status'], self::OFF, true);
        $who = $mode === 'teacher'
            ? $o['group_code'] . ($o['subgroup'] !== null ? '/' . $o['subgroup'] : '')
            : ($o['subgroup'] !== null ? __('assistant.subgroup_short', ['n' => (int) $o['subgroup']]) . ' · ' : '') . $o['effective_teacher_name'];

        return [
            'time' => $o['period_number'] . ' · ' . $this->time($o['start_time']) . '–' . $this->time($o['end_time']),
            'title' => $this->subject($o),
            'meta' => $who . ' · ' . __('schedule.room') . ' ' . $o['effective_classroom_label'] . ($off ? ' · ' . __('assistant.status_' . $o['status']) : ''),
            'off' => $off,
        ];
    }

    private function where(array $o, string $mode): string
    {
        $parts = [];
        if ($mode !== 'teacher' && $o['subgroup'] !== null) {
            $parts[] = __('assistant.subgroup_short', ['n' => (int) $o['subgroup']]);
        }
        if ($o['effective_classroom_label'] !== '—') {
            $parts[] = __('assistant.room_short', ['room' => $o['effective_classroom_label']]);
        }
        $parts[] = $mode === 'teacher'
            ? __('assistant.group_short', ['group' => $o['group_code'] . ($o['subgroup'] !== null ? '/' . $o['subgroup'] : '')])
            : (string) $o['effective_teacher_name'];

        return implode(', ', $parts);
    }

    private function subject(array $o): string
    {
        return localized(['name_ru' => $o['subject_name_ru'], 'name_kk' => $o['subject_name_kk']]);
    }

    private function time(mixed $value): string
    {
        return substr((string) $value, 0, 5);
    }

    private function today(): DateTimeImmutable
    {
        return $this->now->setTime(0, 0);
    }

    private function lessonsCount(int $n): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        $form = $mod10 === 1 && $mod100 !== 11 ? 0 : ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) ? 1 : 2);

        return $n . ' ' . __('assistant.lessons_forms.' . $form);
    }

    /** "Завтра, во вторник 24 ноября" / "в пятницу 28 ноября". */
    public function dayLabel(string $date): string
    {
        $diff = (int) $this->today()->diff(new DateTimeImmutable($date))->format('%r%a');
        $relative = [0 => 'today', 1 => 'tomorrow', 2 => 'day_after', -1 => 'yesterday'][$diff] ?? null;
        $day = $this->weekdayDate($date, true);

        return $relative !== null ? __('assistant.' . $relative) . ', ' . $day : $day;
    }

    /** "вторник, 24 ноября" ($on: "во вторник 24 ноября"). */
    private function weekdayDate(string $date, bool $on = false): string
    {
        $d = new DateTimeImmutable($date);
        $weekday = __(($on ? 'assistant.weekdays_on.' : 'assistant.weekdays.') . $d->format('N'));
        $month = __('assistant.months.' . $d->format('n'));

        return __('assistant.date_format' . ($on ? '_on' : ''), ['weekday' => $weekday, 'd' => (int) $d->format('j'), 'month' => $month]);
    }

    /** Number of distinct lesson periods (subgroups at the same time count once). */
    private function periods(array $lessons): int
    {
        return count(array_unique(array_map(static fn (array $o): string => $o['date'] . '#' . $o['period_number'], $lessons)));
    }

    private static function lcfirst(string $s): string
    {
        return mb_strtolower(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    private static function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    /** @return array{url: string, label: string} */
    private function link(string $path, string $label): array
    {
        return ['url' => url($path), 'label' => $label];
    }

    /**
     * $text is shown in the chat bubble (the details are in $sections); $speech, read aloud, defaults to $text.
     *
     * @return array{text: string, speech: string, sections: list<array<string, mixed>>, link: ?array{url: string, label: string}}
     */
    private function reply(string $text, array $sections = [], ?array $link = null, ?string $speech = null): array
    {
        $clean = static fn (string $s): string => self::ucfirst((string) preg_replace('/(?<!\\.)\\.\\.(?!\\.)/u', '.', $s));

        return ['text' => $clean($text), 'speech' => $clean($speech ?? $text), 'sections' => $sections, 'link' => $link];
    }
}
