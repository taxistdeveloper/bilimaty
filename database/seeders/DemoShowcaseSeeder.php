<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\Session;
use App\Repositories\DocumentRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\ExamRepository;
use App\Repositories\JournalRepository;
use App\Repositories\StudentRepository;
use App\Repositories\SurveyRepository;
use App\Services\DocumentService;
use App\Services\ExamService;
use App\Services\HrService;
use App\Services\JournalService;
use App\Services\SurveyService;
use App\Services\TranscriptService;

/**
 * Showcase demo: fills every module so each role sees a lived-in college for a presentation.
 *  - topics and homework of all lessons;
 *  - curricula for every demo group;
 *  - the previous academic year (2nd-course groups): journals, grades, ratings, exam statements
 *    (closed), a retake, approved journals and issued transcripts;
 *  - exams of the current winter session (planned);
 *  - student requests in every status, surveys with answers, personnel files, orders, leaves,
 *    trainings, a second child for the demo parent.
 * Everything goes through the same services as the UI. Runs once; never in production.
 */
return static function (PDO $pdo, callable $out): void {
    if (Env::get('APP_ENV') === 'production') {
        throw new RuntimeException('Demo data cannot be seeded when APP_ENV=production.');
    }
    if (Database::fetchColumn("SELECT `value` FROM settings WHERE `key` = 'demo_showcase_seeded'") === '1') {
        $out('Demo (showcase): already seeded, skipped.');

        return;
    }
    $schemeId = (int) Database::fetchColumn("SELECT id FROM grading_schemes WHERE status = 'active' ORDER BY id LIMIT 1");
    if ($schemeId === 0 || (int) Database::fetchColumn('SELECT COUNT(*) FROM teacher_subject_assignments') === 0) {
        $out('Demo (showcase): needs the other demo data (journals), skipped.');

        return;
    }

    // Act as the administrator, so every record has an author like in the UI.
    $actor = (int) Database::fetchColumn("SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'admin' ORDER BY ur.user_id LIMIT 1")
        ?: (int) Database::fetchColumn("SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'super_admin' ORDER BY ur.user_id LIMIT 1");
    $previousAuth = Session::get('auth');
    $as = static function (int $userId): void {
        Session::set('auth', ['user_id' => $userId, 'login_at' => time() + 60]);
        Auth::reset();
    };
    $as($actor);

    mt_srand(130);
    $today = date('Y-m-d');
    $id = static fn (string $sql, array $p = []): int => (int) Database::fetchColumn($sql, $p);
    $type = static fn (string $code): int => (int) Database::fetchColumn('SELECT id FROM assessment_types WHERE code = ?', [$code]);
    $subjectId = static fn (string $code): int => (int) Database::fetchColumn('SELECT id FROM subjects WHERE code = ?', [$code]);
    $groupId = static fn (string $code): int => (int) Database::fetchColumn('SELECT id FROM `groups` WHERE code = ?', [$code]);
    $journals = new JournalRepository();
    $journalService = new JournalService($journals);
    $exams = new ExamRepository();
    $examService = new ExamService($exams, $journals);

    // 1. Topics and homework ----------------------------------------------------------------
    $topics = [
        'ООД 01' => ['Фонетика казахского языка', 'Сингармонизм', 'Имя существительное', 'Падежи', 'Имя прилагательное', 'Глагол и его формы', 'Деловая переписка', 'Составление резюме', 'Лексика по специальности', 'Повторение и контроль'],
        'ООД 02' => ['Литература XIX века: обзор', 'А. С. Пушкин. Лирика', 'М. Ю. Лермонтов. «Герой нашего времени»', 'Н. В. Гоголь. «Мёртвые души»', 'Синтаксис сложного предложения', 'Пунктуация', 'Л. Н. Толстой. «Война и мир»', 'Абай Кунанбаев в переводах', 'Сочинение-рассуждение', 'Контрольный диктант'],
        'ООД 05' => ['Множества и операции над ними', 'Функции и их свойства', 'Линейные уравнения и неравенства', 'Квадратные уравнения', 'Тригонометрические функции', 'Показательная функция', 'Логарифмы', 'Производная', 'Применение производной', 'Первообразная и интеграл', 'Решение задач', 'Контрольная работа'],
        'ООД 07' => ['Информация и информационные процессы', 'Системы счисления', 'Устройство компьютера', 'Операционные системы', 'Текстовый процессор', 'Электронные таблицы', 'Формулы и функции в таблицах', 'Презентации', 'Основы алгоритмизации', 'Компьютерные сети и интернет'],
        'БМ 01' => ['Алгоритмы и блок-схемы', 'Переменные и типы данных', 'Ветвления', 'Циклы', 'Массивы', 'Строки', 'Функции', 'Рекурсия', 'Структуры данных', 'Отладка программ', 'Проект: консольное приложение'],
        'БМ 03' => ['Понятие базы данных', 'Реляционная модель', 'Нормализация', 'Язык SQL: SELECT', 'Соединения таблиц', 'Группировка и агрегаты', 'Изменение данных', 'Индексы', 'Транзакции', 'Проектирование БД'],
        'ПМ 02' => ['Модель OSI', 'Стек TCP/IP', 'IP-адресация', 'Маски подсети', 'Коммутаторы', 'Маршрутизаторы', 'Беспроводные сети', 'Настройка DHCP и DNS', 'Сетевая безопасность', 'Практикум: сеть офиса'],
        'ПМ 05' => ['Предмет и метод бухучёта', 'Бухгалтерский баланс', 'Счета и двойная запись', 'План счетов', 'Учёт денежных средств', 'Учёт основных средств', 'Учёт запасов', 'Учёт труда и заработной платы', 'Финансовая отчётность', 'Налоги в учёте'],
    ];
    $homework = ['Конспект, вопросы 1–5', 'Упражнения из практикума', 'Подготовить доклад', 'Решить задачи по теме', 'Повторить материал занятия', 'Выполнить практическое задание'];
    $fillTopics = static function () use ($topics, $homework): int {
        $count = 0;
        foreach (Database::fetchAll(
            'SELECT a.id, sb.code FROM teacher_subject_assignments a JOIN subjects sb ON sb.id = a.subject_id'
        ) as $j) {
            $list = $topics[$j['code']] ?? ['Тема занятия'];
            foreach (Database::fetchAll('SELECT id FROM journal_lessons WHERE assignment_id = ? AND topic IS NULL ORDER BY lesson_date, lesson_period_id', [$j['id']]) as $i => $l) {
                Database::execute('UPDATE journal_lessons SET topic = ?, homework = ? WHERE id = ?', [$list[$i % count($list)], $homework[$i % count($homework)], $l['id']]);
                $count++;
            }
        }

        return $count;
    };
    $out('Demo (showcase): topics for ' . $fillTopics() . ' lessons.');

    // 2. Curricula for every group --------------------------------------------------------------
    $plans = [
        // group => [specialty code, title, rows: [subject, module, study semester, total, theory, practice, lab, control]]
        'ПО-25-1' => ['06130100', 'Программное обеспечение, набор 2025 (демо)', [
            ['ООД 01', 'ООД', 1, 72, 24, 48, 0, 'credit'], ['ООД 02', 'ООД', 1, 72, 36, 36, 0, 'exam'], ['ООД 05', 'ООД', 1, 96, 48, 48, 0, 'exam'], ['ООД 07', 'ООД', 1, 48, 16, 0, 32, 'diff_credit'],
            ['ООД 05', 'ООД', 2, 72, 36, 36, 0, 'exam'], ['БМ 01', 'БМ 01', 2, 120, 40, 0, 80, 'exam'], ['ООД 01', 'ООД', 2, 48, 16, 32, 0, 'credit'],
            ['ООД 05', 'ООД', 3, 72, 36, 36, 0, 'exam'], ['БМ 01', 'БМ 01', 3, 96, 32, 0, 64, 'exam'], ['БМ 03', 'БМ 03', 3, 96, 32, 0, 64, 'diff_credit'],
        ]],
        'ВТ-25-1' => ['06120100', 'Вычислительная техника и информационные сети, набор 2025 (демо)', [
            ['ООД 01', 'ООД', 1, 72, 24, 48, 0, 'credit'], ['ООД 02', 'ООД', 1, 72, 36, 36, 0, 'exam'], ['ООД 05', 'ООД', 1, 96, 48, 48, 0, 'exam'], ['ООД 07', 'ООД', 1, 48, 16, 0, 32, 'diff_credit'],
            ['ООД 05', 'ООД', 2, 72, 36, 36, 0, 'exam'], ['ПМ 02', 'ПМ 02', 2, 96, 32, 0, 64, 'exam'], ['ООД 07', 'ООД', 2, 48, 16, 0, 32, 'diff_credit'],
            ['ООД 05', 'ООД', 3, 72, 36, 36, 0, 'exam'], ['ООД 07', 'ООД', 3, 48, 16, 0, 32, 'diff_credit'], ['ПМ 02', 'ПМ 02', 3, 120, 40, 0, 80, 'exam'],
        ]],
        'УА-26-1' => ['04110100', 'Учет и аудит, набор 2026 (демо)', [
            ['ООД 01', 'ООД', 1, 72, 24, 48, 0, 'credit'], ['ООД 02', 'ООД', 1, 72, 36, 36, 0, 'exam'], ['ООД 05', 'ООД', 1, 96, 48, 48, 0, 'exam'], ['ПМ 05', 'ПМ 05', 1, 96, 48, 48, 0, 'exam'],
            ['ООД 01', 'ООД', 2, 48, 16, 32, 0, 'credit'], ['ПМ 05', 'ПМ 05', 2, 120, 40, 80, 0, 'exam'],
        ]],
    ];
    foreach ($plans as $code => [$specialtyCode, $title, $rows]) {
        $group = Database::fetch('SELECT * FROM `groups` WHERE code = ?', [$code]);
        if ($group === null || $group['curriculum_id'] !== null) {
            continue;
        }
        $specialty = $id('SELECT id FROM specialties WHERE code = ?', [$specialtyCode]);
        Database::execute(
            "INSERT INTO curricula (specialty_id, qualification_id, title, admission_year, study_form, base_education, semesters_count, status, approved_at)
             VALUES (?, ?, ?, ?, 'full_time', 'basic_secondary', 8, 'approved', NOW())",
            [$specialty, $group['qualification_id'], $title, $group['admission_year']]
        );
        $planId = Database::lastInsertId();
        foreach ($rows as $i => [$subject, $module, $semester, $total, $theory, $practice, $lab, $control]) {
            Database::execute(
                'INSERT INTO curriculum_subjects (curriculum_id, subject_id, module_code, semester, hours_total, hours_theory, hours_practice, hours_lab, control_form, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$planId, $subjectId($subject), $module, $semester, $total, $theory, $practice, $lab, $control, $i]
            );
        }
        Database::execute('UPDATE `groups` SET curriculum_id = ? WHERE id = ?', [$planId, $group['id']]);
    }
    $out('Demo (showcase): curricula linked to every group.');

    // 3. Previous academic year of the 2nd-course groups ------------------------------------------
    $prevYear = Database::fetch("SELECT * FROM academic_years WHERE end_date < ? ORDER BY end_date DESC LIMIT 1", [$today]);
    $issued = 0;
    if ($prevYear !== null) {
        $yearStatus = (string) $prevYear['status'];
        // Archived years are read-only in the UI; open it for the time of seeding, as if the year were running.
        Database::execute("UPDATE academic_years SET status = 'planned' WHERE id = ?", [$prevYear['id']]);
        $semesters = Database::fetchAll('SELECT * FROM semesters WHERE academic_year_id = ? ORDER BY number', [$prevYear['id']]);
        $teacherOf = ['ООД 01' => 6, 'ООД 02' => 2, 'ООД 05' => 3, 'ООД 07' => 4, 'БМ 01' => 4, 'ПМ 02' => 1];
        $history = [
            'ПО-25-1' => [1 => ['ООД 01', 'ООД 02', 'ООД 05', 'ООД 07'], 2 => ['ООД 05', 'БМ 01', 'ООД 01']],
            'ВТ-25-1' => [1 => ['ООД 01', 'ООД 02', 'ООД 05', 'ООД 07'], 2 => ['ООД 05', 'ПМ 02', 'ООД 07']],
        ];
        $rating1 = $type('rating1');
        $rating2 = $type('rating2');
        $examType = $type('exam');
        $closed = 0;
        $retakes = 0;
        $examDay = [];
        foreach ($history as $groupCode => $bySemester) {
            $gid = $groupId($groupCode);
            foreach ($semesters as $semester) {
                $number = (int) $semester['number'];
                foreach ($bySemester[$number] ?? [] as $k => $subjectCode) {
                    $sid = $subjectId($subjectCode);
                    if ($id('SELECT id FROM teacher_subject_assignments WHERE semester_id = ? AND group_id = ? AND subject_id = ?', [$semester['id'], $gid, $sid]) !== 0) {
                        continue;
                    }
                    Database::execute(
                        "INSERT INTO teacher_subject_assignments (semester_id, group_id, subject_id, teacher_id, grading_scheme_id, status) VALUES (?, ?, ?, ?, ?, 'open')",
                        [$semester['id'], $gid, $sid, $teacherOf[$subjectCode], $schemeId]
                    );
                    $journal = $journals->find(Database::lastInsertId());
                    // Weekly lessons, two academic hours, until the end of theory (two weeks before the semester end).
                    $date = new DateTimeImmutable((string) $semester['start_date']);
                    $date = $date->modify('+' . (($k + (int) $date->format('N') <= 5 ? $k : 0)) . ' days');
                    $lastDay = (new DateTimeImmutable((string) $semester['end_date']))->modify('-14 days');
                    while ($date <= $lastDay) {
                        if ((int) $date->format('N') <= 5) {
                            $journalService->saveLesson($journal, null, ['lesson_date' => $date->format('Y-m-d'), 'lesson_period_id' => 1 + ($k % 3),
                                'lesson_type' => $k % 2 === 0 ? 'lecture' : 'practice', 'topic' => null, 'homework' => null, 'note' => null, 'hours' => 2,
                                'conducted_by' => $teacherOf[$subjectCode], 'schedule_entry_id' => null]);
                        }
                        $date = $date->modify('+7 days');
                    }
                    $students = $journals->students($journal);
                    // A student's level is stable across subjects, with some noise.
                    foreach ($journals->lessons((int) $journal['id']) as $lesson) {
                        foreach ($students as $st) {
                            $base = 55 + ((int) $st['id'] * 37) % 40;
                            $roll = mt_rand(1, 100);
                            $value = match (true) {
                                $roll <= 4 => 'н',
                                $roll <= 6 => 'у',
                                $roll <= 60 => (string) max(30, min(100, $base + mt_rand(-12, 12))),
                                default => null,
                            };
                            if ($value !== null) {
                                $journalService->setLessonCell($journal, $lesson, (int) $st['id'], $value);
                            }
                        }
                    }
                    $weak = (int) ($students[array_key_last($students)]['id'] ?? 0); // one student struggles in the first subject
                    foreach ($students as $st) {
                        $base = 55 + ((int) $st['id'] * 37) % 40;
                        $low = $k === 0 && (int) $st['id'] === $weak;
                        $journalService->setAssessment($journal, $rating1, (int) $st['id'], (string) ($low ? 42 : max(40, min(100, $base + mt_rand(-10, 10)))));
                        $journalService->setAssessment($journal, $rating2, (int) $st['id'], (string) ($low ? 38 : max(40, min(100, $base + mt_rand(-10, 10)))));
                    }

                    // Exam statement in the session after the semester, closed: values go into the journal.
                    $examDate = (new DateTimeImmutable((string) $semester['end_date']))->modify('-' . (10 - 2 * $k - ($groupCode === 'ВТ-25-1' ? 1 : 0)) . ' days');
                    while ((int) $examDate->format('N') > 5 || in_array($examDate->format('Y-m-d'), $examDay[$groupCode] ?? [], true)) {
                        $examDate = $examDate->modify('+1 day');
                    }
                    $examDay[$groupCode][] = $examDate->format('Y-m-d');
                    $journal = $journals->find((int) $journal['id']);
                    $examId = $examService->save([
                        'assignment_id' => (int) $journal['id'], 'assessment_type_id' => $examType, 'exam_form' => $k % 2 === 0 ? 'written' : 'oral',
                        'period_start' => null, 'exam_date' => $examDate->format('Y-m-d'), 'start_time' => $groupCode === 'ВТ-25-1' ? '13:00:00' : '09:00:00',
                        'classroom_id' => $groupCode === 'ВТ-25-1' ? 3 : 1, 'examiner_teacher_id' => $teacherOf[$subjectCode], 'note' => null,
                    ]);
                    $exam = $exams->find($examId);
                    $rows = [];
                    foreach ($examService->statement($exam) as $row) {
                        $base = 55 + ((int) $row['id'] * 37) % 40;
                        $rows[(int) $row['id']] = (int) $row['id'] === $weak && $k === 0
                            ? ['admission' => 'admitted', 'attendance' => 'present', 'value' => '25']
                            : ['admission' => 'admitted', 'attendance' => 'present', 'value' => (string) max(50, min(100, $base + mt_rand(-10, 12)))];
                    }
                    $examService->saveStatement($exam, $rows);
                    $examService->close($exams->find($examId));
                    $closed++;

                    // The weak student failed: a retake two weeks later, passed.
                    if ($k === 0 && $weak !== 0) {
                        $exam = $exams->find($examId);
                        $retakeId = $examService->scheduleRetake($exam, ['student_id' => $weak, 'retake_date' => $examDate->modify('+14 days')->format('Y-m-d'),
                            'examiner_teacher_id' => $teacherOf[$subjectCode], 'reason' => 'Неудовлетворительная оценка на экзамене', 'order_number' => '№ ' . (30 + $number) . '-У']);
                        $examService->completeRetake($exams->find($examId), $exams->retake($retakeId), '72', false);
                        $retakes++;
                    }
                    $journal = $journals->find((int) $journal['id']);
                    $journalService->submit($journal);
                    $journalService->approve($journals->find((int) $journal['id']));
                }
            }
        }
        $out("Demo (showcase): previous year — {$closed} closed exam statements, {$retakes} retakes, journals approved.");
        $out('Demo (showcase): topics for ' . $fillTopics() . ' more lessons.');

        // Transcripts for the best students of ПО-25-1.
        $transcripts = new TranscriptService();
        foreach (Database::fetchAll(
            "SELECT s.id FROM students s JOIN `groups` g ON g.id = s.current_group_id WHERE g.code IN ('ПО-25-1', 'ВТ-25-1') ORDER BY s.id LIMIT 4"
        ) as $s) {
            $transcripts->issue((int) $s['id']);
            $issued++;
        }
        Database::execute('UPDATE academic_years SET status = ? WHERE id = ?', [$yearStatus, $prevYear['id']]);
        $out("Demo (showcase): {$issued} transcripts issued.");
    }

    // 4. Winter session of the current semester: planned exams -------------------------------------
    $session = Database::fetch("SELECT start_date FROM academic_calendar_events WHERE type = 'exam_session' AND start_date >= ? ORDER BY start_date LIMIT 1", [$today]);
    $planned = 0;
    if ($session !== null) {
        $current = Database::fetchAll(
            "SELECT a.id, a.group_id, a.teacher_id, g.code FROM teacher_subject_assignments a JOIN semesters s ON s.id = a.semester_id JOIN `groups` g ON g.id = a.group_id
              WHERE s.start_date <= ? AND s.end_date >= ? ORDER BY g.code, a.id",
            [$today, $today]
        );
        $slot = [];
        foreach ($current as $j) {
            $n = $slot[$j['group_id']] = ($slot[$j['group_id']] ?? -1) + 1;
            $day = (new DateTimeImmutable((string) $session['start_date']))->modify('+' . ($n * 2) . ' days');
            while ((int) $day->format('N') > 5) {
                $day = $day->modify('+1 day');
            }
            $groupIndex = array_search((int) $j['group_id'], array_keys($slot), true);
            $examService->save([
                'assignment_id' => (int) $j['id'], 'assessment_type_id' => $type('exam'), 'exam_form' => $n % 2 === 0 ? 'written' : 'oral',
                'period_start' => null, 'exam_date' => $day->format('Y-m-d'), 'start_time' => ['09:00:00', '11:00:00', '13:00:00', '15:00:00'][$groupIndex % 4],
                'classroom_id' => [1, 2, 3, 6][$groupIndex % 4], 'examiner_teacher_id' => (int) $j['teacher_id'], 'note' => null,
            ]);
            $planned++;
        }
    }
    $out("Demo (showcase): {$planned} exams planned for the winter session.");

    // 5. Student requests (documents) in every status --------------------------------------------
    $studentRepo = new StudentRepository();
    $documents = new DocumentService();
    $documentRepo = new DocumentRepository();
    $docType = static fn (string $code): array => Database::fetch('SELECT * FROM document_types WHERE code = ?', [$code]) ?? [];
    $studentsList = array_column(Database::fetchAll('SELECT s.id, s.user_id FROM students s ORDER BY s.id'), null, 'id');
    $ids = array_keys($studentsList);
    $requests = [
        // student index, type, purpose / details, actions taken by the office
        [0, 'study_certificate', 'Для предоставления в военкомат', ['take', 'approve', 'complete']],
        [3, 'study_certificate', 'Для получения социальной помощи', ['take', 'approve']],
        [7, 'study_certificate', 'По месту работы родителей', ['take']],
        [12, 'study_certificate', 'Для оформления проездного билета', []],
        [15, 'transcript', 'Для перевода в другой колледж', ['take', 'request_info']],
        [21, 'study_certificate', 'В банк', ['reject']],
        [26, 'other', 'Прошу выдать копию приказа о зачислении для подачи в ЦОН.', []],
        [33, 'study_certificate', 'В налоговый орган', ['take', 'approve', 'complete']],
    ];
    $made = 0;
    foreach ($requests as [$index, $code, $text, $actions]) {
        if (!isset($ids[$index])) {
            continue;
        }
        $student = $studentRepo->find($ids[$index]);
        $t = $docType($code);
        if ($student === null || $t === []) {
            continue;
        }
        $as((int) $student['user_id']);
        $requestId = $documents->save($student, $t, $code === 'other' ? ['details' => $text, 'copies' => 1] : ['purpose' => $text, 'copies' => 1], null, null, true);
        $as($actor);
        foreach ($actions as $action) {
            $comment = match ($action) {
                'request_info' => 'Уточните, нужен ли транскрипт на казахском или русском языке.',
                'reject' => 'Справка этого вида уже выдана вам на этой неделе.',
                default => '',
            };
            $documents->transition($documentRepo->find($requestId) ?? [], $action, $comment);
        }
        $made++;
    }
    $out("Demo (showcase): {$made} student requests.");

    // 6. Surveys with answers -------------------------------------------------------------------
    $surveys = new SurveyService();
    $surveyRepo = new SurveyRepository();
    $build = static function (array $input, array $questions) use ($surveys, $surveyRepo): array {
        $surveyId = $surveys->save($input, null);
        foreach ($questions as $q) {
            $surveys->saveQuestion($surveyRepo->find($surveyId), $q + ['is_required' => '1', 'text_kk' => $q['text_ru']], null);
        }
        $surveys->publish($surveyRepo->find($surveyId));

        return $surveyRepo->find($surveyId);
    };
    $answer = static function (array $survey, int $userId) use ($surveys, $surveyRepo): bool {
        $respondent = $surveyRepo->respondent($userId);
        if ($respondent === null || $surveys->cannotAnswer($survey, $userId, $respondent) !== null) {
            return false;
        }
        $input = [];
        foreach ($surveyRepo->questions((int) $survey['id']) as $q) {
            $options = array_map(static fn (array $o): int => (int) $o['id'], $q['options']);
            $input[(int) $q['id']] = match ($q['type']) {
                'single' => (string) $options[min(count($options) - 1, (int) floor(mt_rand(0, 99) ** 2 / 10000 * count($options)))],
                'multiple' => array_map('strval', array_slice($options, mt_rand(0, max(0, count($options) - 2)), mt_rand(1, 2))),
                'scale' => (string) max((int) $q['scale_min'], min((int) $q['scale_max'], (int) $q['scale_max'] - (mt_rand(0, 9) < 6 ? mt_rand(0, 1) : mt_rand(1, 3)))),
                default => ['Больше практики на компьютерах', 'Всё нравится', 'Удобное расписание', 'Хотелось бы больше консультаций перед экзаменами', 'Нужен ремонт в аудитории 105', ''][mt_rand(0, 5)],
            };
        }
        $surveys->respond($survey, $userId, $respondent, $input);

        return true;
    };
    $quality = $build([
        'title_ru' => 'Удовлетворённость качеством обучения', 'title_kk' => 'Оқыту сапасына қанағаттану',
        'description_ru' => 'Анонимная анкета для студентов. Помогите нам сделать обучение лучше.', 'description_kk' => 'Студенттерге арналған жасырын сауалнама.',
        'starts_on' => date('Y-m-d', strtotime('-10 days')), 'ends_on' => date('Y-m-d', strtotime('+20 days')), 'audience' => 'students', 'is_anonymous' => '1',
    ], [
        ['type' => 'scale', 'text_ru' => 'Насколько вы довольны обучением в колледже?', 'scale_min' => 1, 'scale_max' => 5, 'min_label_ru' => 'Совсем не доволен', 'max_label_ru' => 'Очень доволен'],
        ['type' => 'single', 'text_ru' => 'Как вы оцениваете расписание занятий?', 'options' => "Удобное|Ыңғайлы\nСкорее удобное|Көбінесе ыңғайлы\nНеудобное|Ыңғайсыз"],
        ['type' => 'multiple', 'text_ru' => 'Что стоит улучшить?', 'options' => "Оснащение аудиторий|Аудиториялар жабдығы\nДоступ к Wi-Fi|Wi-Fi қолжетімділігі\nБиблиотека|Кітапхана\nСтоловая|Асхана\nСпортзал|Спортзал"],
        ['type' => 'text', 'text_ru' => 'Ваши предложения', 'is_required' => '0'],
    ]);
    $responses = 0;
    foreach (array_slice($studentsList, 0, 28) as $s) {
        $responses += $answer($quality, (int) $s['user_id']) ? 1 : 0;
    }
    $teachersSurvey = $build([
        'title_ru' => 'Методическая работа: потребности преподавателей', 'title_kk' => 'Әдістемелік жұмыс: оқытушылардың қажеттіліктері',
        'description_ru' => 'Анкета для планирования курсов повышения квалификации.', 'description_kk' => 'Біліктілікті арттыру курстарын жоспарлауға арналған сауалнама.',
        'starts_on' => date('Y-m-d', strtotime('-5 days')), 'ends_on' => date('Y-m-d', strtotime('+10 days')), 'audience' => 'teachers', 'is_anonymous' => '0',
    ], [
        ['type' => 'multiple', 'text_ru' => 'Какие курсы вам интересны?', 'options' => "Цифровые инструменты в обучении|Оқытудағы цифрлық құралдар\nКритериальное оценивание|Критериалды бағалау\nИнклюзивное образование|Инклюзивті білім беру\nДуальное обучение|Дуальды оқыту"],
        ['type' => 'scale', 'text_ru' => 'Насколько вы загружены методической работой?', 'scale_min' => 1, 'scale_max' => 5, 'min_label_ru' => 'Слабо', 'max_label_ru' => 'Очень сильно'],
    ]);
    foreach (Database::fetchAll("SELECT user_id FROM teachers WHERE status = 'active' ORDER BY id LIMIT 4") as $t) {
        $responses += $answer($teachersSurvey, (int) $t['user_id']) ? 1 : 0;
    }
    // A finished survey: answered while it ran, then closed with its period in the past.
    $adaptation = $build([
        'title_ru' => 'Адаптация первокурсников', 'title_kk' => 'Бірінші курс студенттерінің бейімделуі',
        'description_ru' => 'Опрос студентов 1 курса в первый месяц обучения.', 'description_kk' => '1-курс студенттерінің сауалнамасы.',
        'starts_on' => date('Y-m-d', strtotime('-1 day')), 'ends_on' => date('Y-m-d', strtotime('+1 day')), 'audience' => 'students', 'course' => '1', 'is_anonymous' => '1',
    ], [
        ['type' => 'scale', 'text_ru' => 'Насколько легко вам было освоиться в колледже?', 'scale_min' => 1, 'scale_max' => 5, 'min_label_ru' => 'Трудно', 'max_label_ru' => 'Легко'],
        ['type' => 'single', 'text_ru' => 'Помогает ли вам куратор?', 'options' => "Да|Иә\nИногда|Кейде\nНет|Жоқ"],
    ]);
    foreach ($studentsList as $s) {
        $responses += $answer($adaptation, (int) $s['user_id']) ? 1 : 0;
    }
    $surveys->close($surveyRepo->find((int) $adaptation['id']));
    Database::execute('UPDATE surveys SET starts_on = ?, ends_on = ? WHERE id = ?', [date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('-45 days')), $adaptation['id']]);
    $surveys->save([
        'title_ru' => 'Оценка практики (черновик)', 'title_kk' => 'Практиканы бағалау (жоба)', 'description_ru' => 'Готовится к летней практике.',
        'starts_on' => date('Y-m-d', strtotime('+150 days')), 'ends_on' => date('Y-m-d', strtotime('+170 days')), 'audience' => 'students', 'course' => '2', 'is_anonymous' => '0',
    ], null);
    $out("Demo (showcase): 4 surveys, {$responses} responses.");

    // 7. Personnel: files, orders, leaves, trainings ------------------------------------------------
    $hr = new HrService();
    $employees = new EmployeeRepository();
    $registered = $hr->registerTeachers();
    $staff = [
        // role, position, category, rate, hire date
        ['admin', 'Администратор информационной системы', 'administrative', '1.00', '2019-08-26'],
        ['deputy_director', 'Заместитель директора по учебной работе', 'administrative', '1.00', '2015-08-24'],
        ['academic_office', 'Заведующая учебной частью', 'administrative', '1.00', '2017-09-01'],
        ['hr', 'Специалист отдела кадров', 'administrative', '1.00', '2020-02-03'],
    ];
    $number = 100;
    foreach ($staff as [$role, $position, $category, $rate, $hire]) {
        $userId = $id('SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = ? ORDER BY ur.user_id LIMIT 1', [$role]);
        if ($userId === 0 || $employees->findByUser($userId) !== null) {
            continue;
        }
        $hr->create(['personnel_number' => 'A' . ++$number, 'iin' => null, 'birth_date' => null, 'gender' => null, 'address' => null, 'education' => 'Высшее',
            'department_id' => null, 'position' => $position, 'staff_category' => $category, 'employment_type' => 'main', 'rate' => $rate, 'hire_date' => $hire,
            'prior_experience_months' => mt_rand(12, 120), 'annual_leave_days' => null, 'qualification_category' => null, 'category_assigned_on' => null,
            'category_valid_until' => null, 'note' => null], $userId);
    }
    // Support staff without an account, hired by a signed order this autumn.
    $supportId = $hr->create(['last_name' => 'Жумабеков', 'first_name' => 'Серик', 'middle_name' => 'Амангельдыевич', 'phone' => null, 'email' => null,
        'personnel_number' => 'S' . ++$number, 'iin' => null, 'birth_date' => '1978-04-12', 'gender' => 'male', 'address' => null, 'education' => 'Среднее специальное',
        'department_id' => null, 'position' => 'Лаборант компьютерных классов', 'staff_category' => 'support', 'employment_type' => 'main', 'rate' => '1.00',
        'hire_date' => null, 'prior_experience_months' => 60, 'annual_leave_days' => null, 'qualification_category' => null, 'category_assigned_on' => null,
        'category_valid_until' => null, 'note' => null], null);
    $support = $employees->find($supportId);
    $hireOrder = $hr->createOrder($support, ['type' => 'hire', 'order_date' => date('Y-m-d', strtotime('-40 days')), 'effective_date' => date('Y-m-d', strtotime('-38 days')),
        'basis' => 'Заявление работника', 'position' => 'Лаборант компьютерных классов', 'department_id' => null, 'rate' => '1.00', 'staff_category' => 'support', 'employment_type' => 'main']);
    $hr->signOrder($employees->order($hireOrder));

    // Teacher files: categories (one expiring soon), trainings (one teacher overdue), orders, leaves.
    $teacherFiles = Database::fetchAll('SELECT e.*, t.id AS teacher_id FROM employees e JOIN teachers t ON t.user_id = e.user_id ORDER BY t.id');
    $categories = [['highest', '-4 years', '+1 year'], ['first', '-2 years', '+3 years'], ['second', '-5 years', '+3 months'], ['moderator', '-1 year', '+4 years'], ['expert', '-3 years', '+2 years'], ['none', null, null]];
    foreach ($teacherFiles as $i => $e) {
        [$cat, $from, $until] = $categories[$i % count($categories)];
        Database::execute('UPDATE employees SET qualification_category = ?, category_assigned_on = ?, category_valid_until = ?, education = ?, prior_experience_months = ? WHERE id = ?', [
            $cat, $from !== null ? date('Y-m-d', strtotime($from)) : null, $until !== null ? date('Y-m-d', strtotime($until)) : null,
            'Высшее педагогическое', [36, 0, 84, 12, 24, 6][$i % 6], $e['id'],
        ]);
        if ($i !== 5) {
            $hr->addTraining($employees->find((int) $e['id']), ['title' => ['Цифровые инструменты в обучении', 'Критериальное оценивание в ТиПО', 'Модульное обучение и кредитная система', 'Инклюзивное образование', 'Дуальное обучение: организация практики'][$i % 5],
                'provider' => 'АО «НЦПК «Өрлеу»', 'hours' => [72, 36, 80, 72, 36][$i % 5], 'start_date' => date('Y-m-d', strtotime('-' . (8 + $i * 5) . ' months')),
                'end_date' => date('Y-m-d', strtotime('-' . (8 + $i * 5) . ' months +12 days')), 'certificate_number' => 'ПК-' . (2400 + $i * 17)]);
        }
    }
    // The last teacher had courses six years ago: shows up in HR reminders.
    if (isset($teacherFiles[5])) {
        $hr->addTraining($employees->find((int) $teacherFiles[5]['id']), ['title' => 'Основы педагогического мастерства', 'provider' => 'ИПК', 'hours' => 72,
            'start_date' => date('Y-m-d', strtotime('-6 years -1 month')), 'end_date' => date('Y-m-d', strtotime('-6 years')), 'certificate_number' => null]);
        $rateOrder = $hr->createOrder($employees->find((int) $teacherFiles[5]['id']), ['type' => 'rate_change', 'order_date' => $today, 'effective_date' => date('Y-m-d', strtotime('+8 days')),
            'basis' => 'Служебная записка заведующего отделением', 'rate' => '1.25']);
        unset($rateOrder); // left as a draft for HR to sign during the demo
    }
    if (isset($teacherFiles[1])) {
        $e = $employees->find((int) $teacherFiles[1]['id']);
        $hr->addLeave($e, ['type' => 'annual', 'start_date' => date('Y', strtotime('-1 year')) . '-07-01', 'end_date' => date('Y', strtotime('-1 year')) . '-08-25', 'reason' => null, 'document_number' => null, 'destination' => null], true);
        $hr->addLeave($e, ['type' => 'sick', 'start_date' => date('Y-m-d', strtotime('-20 days')), 'end_date' => date('Y-m-d', strtotime('-16 days')), 'reason' => 'ОРВИ', 'document_number' => 'ЛН-' . mt_rand(10000, 99999), 'destination' => null], true);
    }
    if (isset($teacherFiles[2])) {
        $as((int) $teacherFiles[2]['user_id']);
        $hr->addLeave($employees->find((int) $teacherFiles[2]['id']), ['type' => 'study', 'start_date' => date('Y-m-d', strtotime('+14 days')), 'end_date' => date('Y-m-d', strtotime('+18 days')),
            'reason' => 'Сессия в магистратуре', 'document_number' => null, 'destination' => null], false);
        $as($actor);
    }
    if (isset($teacherFiles[3])) {
        $hr->addLeave($employees->find((int) $teacherFiles[3]['id']), ['type' => 'business_trip', 'start_date' => date('Y-m-d', strtotime('+3 days')), 'end_date' => date('Y-m-d', strtotime('+5 days')),
            'reason' => 'Областной семинар преподавателей информатики', 'document_number' => null, 'destination' => 'г. Талдыкорган'], true);
    }
    $out("Demo (showcase): {$registered} teacher files, staff files, orders, leaves and trainings.");

    // 8. The demo parent gets a second child -------------------------------------------------------
    $parent = $id("SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'parent' ORDER BY ur.user_id LIMIT 1");
    $second = $id("SELECT s.id FROM students s JOIN `groups` g ON g.id = s.current_group_id WHERE g.code = 'ПО-25-1' ORDER BY s.id LIMIT 1");
    if ($parent !== 0 && $second !== 0 && $id('SELECT COUNT(*) FROM student_guardians WHERE user_id = ? AND student_id = ?', [$parent, $second]) === 0) {
        Database::execute("INSERT INTO student_guardians (student_id, user_id, relation, created_by) VALUES (?, ?, 'guardian', ?)", [$second, $parent, $actor]);
    }

    // "View as role" shows the student with the fullest story: 2nd course, a closed first year,
    // a transcript, and the second child of the demo parent.
    $persona = $id('SELECT user_id FROM students WHERE id = ?', [$second]);
    if ($persona !== 0) {
        Database::execute(
            "INSERT INTO settings (`key`, `value`, `type`) VALUES ('demo_role_users', ?, 'string') ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [json_encode(['student' => $persona])]
        );
    }

    Session::set('auth', $previousAuth);
    Auth::reset();
    Database::execute("INSERT INTO settings (`key`, `value`, `type`) VALUES ('demo_showcase_seeded', '1', 'bool') ON DUPLICATE KEY UPDATE `value` = '1'");
};
