<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Services\StudentService;
use App\Services\TeacherService;

/**
 * Demo data for local testing: departments, specialties, qualifications, subjects,
 * classrooms, teachers, groups and students. Uses the same services as the UI, so
 * accounts and group history are created consistently.
 *
 * Run: php cli.php db:seed --demo   (refused when APP_ENV=production; runs once)
 * Demo people get random passwords; reset them from "Users" when needed.
 * IINs are intentionally left empty so no real person's identifier is ever generated.
 */
return static function (PDO $pdo, callable $out): void {
    if (Env::get('APP_ENV') === 'production') {
        throw new RuntimeException('Demo data cannot be seeded when APP_ENV=production.');
    }
    if (Database::fetchColumn("SELECT `value` FROM settings WHERE `key` = 'demo_seeded'") === '1') {
        $out('Demo: already seeded, skipped.');

        return;
    }
    $yearId = (int) Database::fetchColumn("SELECT id FROM academic_years WHERE status = 'active' LIMIT 1");
    if ($yearId === 0) {
        throw new RuntimeException('Demo: no active academic year, run php cli.php db:seed first.');
    }

    mt_srand(2026);
    $insert = static function (string $table, array $row): int {
        $columns = array_keys($row);
        Database::execute(
            'INSERT INTO ' . Database::identifier($table) . ' (' . implode(', ', array_map([Database::class, 'identifier'], $columns))
            . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
            array_values($row)
        );

        return Database::lastInsertId();
    };

    // Dictionaries ---------------------------------------------------------
    $it = $insert('departments', ['code' => 'IT', 'name_ru' => 'Отделение информационных технологий', 'name_kk' => 'Ақпараттық технологиялар бөлімі']);
    $eco = $insert('departments', ['code' => 'ECO', 'name_ru' => 'Экономическое отделение', 'name_kk' => 'Экономика бөлімі']);

    $software = $insert('specialties', ['department_id' => $it, 'code' => '06130100', 'name_ru' => 'Программное обеспечение', 'name_kk' => 'Бағдарламалық қамтамасыз ету']);
    $networks = $insert('specialties', ['department_id' => $it, 'code' => '06120100', 'name_ru' => 'Вычислительная техника и информационные сети', 'name_kk' => 'Есептеу техникасы және ақпараттық желілер']);
    $accounting = $insert('specialties', ['department_id' => $eco, 'code' => '04110100', 'name_ru' => 'Учет и аудит', 'name_kk' => 'Есеп және аудит']);

    $qSoftware = $insert('qualifications', ['specialty_id' => $software, 'code' => '4S06130103', 'name_ru' => 'Разработчик программного обеспечения', 'name_kk' => 'Бағдарламалық қамтамасыз етуді әзірлеуші']);
    $qNetworks = $insert('qualifications', ['specialty_id' => $networks, 'code' => '4S06120102', 'name_ru' => 'Техник по информационным сетям', 'name_kk' => 'Ақпараттық желілер технигі']);
    $qAccounting = $insert('qualifications', ['specialty_id' => $accounting, 'code' => '4S04110102', 'name_ru' => 'Бухгалтер', 'name_kk' => 'Бухгалтер']);

    $subjects = [
        ['ООД 01', 'Казахский язык', 'Қазақ тілі', null], ['ООД 02', 'Русский язык и литература', 'Орыс тілі мен әдебиеті', null],
        ['ООД 05', 'Математика', 'Математика', null], ['ООД 07', 'Информатика', 'Информатика', $it],
        ['БМ 01', 'Основы программирования', 'Бағдарламалау негіздері', $it], ['БМ 03', 'Базы данных', 'Деректер қоры', $it],
        ['ПМ 02', 'Компьютерные сети', 'Компьютерлік желілер', $it], ['ПМ 05', 'Бухгалтерский учет', 'Бухгалтерлік есеп', $eco],
    ];
    foreach ($subjects as [$code, $ru, $kk, $department]) {
        $insert('subjects', ['code' => $code, 'name_ru' => $ru, 'name_kk' => $kk, 'department_id' => $department]);
    }
    $rooms = [['101', 'lecture', 40], ['105', 'practice', 30], ['201', 'computer', 16], ['202', 'computer', 16], ['210', 'laboratory', 20], ['301', 'lecture', 60]];
    foreach ($rooms as [$number, $type, $capacity]) {
        $insert('classrooms', ['number' => $number, 'building' => '', 'type' => $type, 'capacity' => $capacity, 'department_id' => in_array($type, ['computer', 'laboratory'], true) ? $it : null]);
    }
    $out('Demo: dictionaries created.');

    // Teachers --------------------------------------------------------------
    $teacherService = new TeacherService();
    $teacherData = [
        ['Сейтқали', 'Айгүл', 'Бекболатқызы', $it, 'Заведующая отделением'],
        ['Петров', 'Андрей', 'Викторович', $it, 'Преподаватель'],
        ['Нұрланова', 'Жанар', 'Ерланқызы', $it, 'Преподаватель'],
        ['Ким', 'Ольга', 'Сергеевна', $it, 'Преподаватель'],
        ['Омаров', 'Данияр', 'Маратұлы', $eco, 'Заведующий отделением'],
        ['Смагулова', 'Динара', 'Каиртаевна', $eco, 'Преподаватель'],
    ];
    $teachers = [];
    foreach ($teacherData as $i => [$last, $first, $middle, $department, $position]) {
        $teachers[] = $teacherService->create([
            'last_name' => $last, 'first_name' => $first, 'middle_name' => $middle, 'email' => null, 'phone' => null,
            'login' => null, 'locale' => $i % 2 === 0 ? 'kk' : 'ru', 'department_id' => $department,
            'employee_number' => sprintf('T%04d', $i + 1), 'position' => $position, 'academic_degree' => null,
            'hire_date' => sprintf('20%02d-09-01', 10 + $i), 'status' => 'active',
        ])['id'];
    }
    // Department heads get the department_head role, curators the curator role.
    $grant = static function (int $teacherId, string $role): void {
        Database::execute(
            'INSERT IGNORE INTO user_roles (user_id, role_id) SELECT t.user_id, r.id FROM teachers t, roles r WHERE t.id = ? AND r.slug = ?',
            [$teacherId, $role]
        );
    };
    Database::execute('UPDATE departments SET head_teacher_id = ? WHERE id = ?', [$teachers[0], $it]);
    Database::execute('UPDATE departments SET head_teacher_id = ? WHERE id = ?', [$teachers[4], $eco]);
    $grant($teachers[0], 'department_head');
    $grant($teachers[4], 'department_head');
    $out('Demo: ' . count($teachers) . ' teachers created.');

    // Groups ------------------------------------------------------------------
    $year = (int) date('Y');
    $groupData = [
        ['ПО-26-1', 'Программное обеспечение, 1 курс', $software, $qSoftware, 1, $year, $teachers[1], 'kk'],
        ['ПО-25-1', 'Программное обеспечение, 2 курс', $software, $qSoftware, 2, $year - 1, $teachers[2], 'ru'],
        ['ВТ-25-1', 'Вычислительная техника, 2 курс', $networks, $qNetworks, 2, $year - 1, $teachers[3], 'kk'],
        ['УА-26-1', 'Учет и аудит, 1 курс', $accounting, $qAccounting, 1, $year, $teachers[5], 'ru'],
    ];
    $groups = [];
    foreach ($groupData as [$code, $name, $specialty, $qualification, $course, $admission, $curator, $language]) {
        $groups[] = [$insert('groups', [
            'code' => $code, 'name' => $name, 'specialty_id' => $specialty, 'qualification_id' => $qualification,
            'course' => $course, 'admission_year' => $admission, 'academic_year_id' => $yearId,
            'curator_teacher_id' => $curator, 'study_form' => 'full_time', 'study_language' => $language,
        ]), $admission];
        $grant($curator, 'curator');
    }
    $out('Demo: ' . count($groups) . ' groups created.');

    // Students ---------------------------------------------------------------
    $kkLast = ['Абдрахманов', 'Байжанов', 'Ерғалиев', 'Жұмабаев', 'Қасымов', 'Мұхамеджанов', 'Нұрпейісов', 'Сәрсенбаев', 'Тоқтаров', 'Ұзақов'];
    $kkFirst = ['Әлихан', 'Нұрсұлтан', 'Ерасыл', 'Арман', 'Бауыржан', 'Дамир', 'Ернар', 'Мирас', 'Санжар', 'Темірлан'];
    $kkFirstF = ['Аружан', 'Айым', 'Дана', 'Жансая', 'Әсем', 'Мадина', 'Томирис', 'Ақбота', 'Інжу', 'Камила'];
    $ruLast = ['Иванов', 'Смирнов', 'Кузнецов', 'Попов', 'Васильев', 'Новиков', 'Морозов', 'Волков', 'Соколов', 'Лебедев'];
    $ruFirst = ['Артём', 'Максим', 'Даниил', 'Кирилл', 'Егор', 'Илья', 'Тимур', 'Роман', 'Денис', 'Никита'];
    $ruFirstF = ['Анна', 'Мария', 'Полина', 'Алина', 'Виктория', 'Ксения', 'Дарья', 'Елизавета', 'София', 'Вероника'];

    $studentService = new StudentService();
    $count = 0;
    foreach ($groups as $index => [$groupId, $admission]) {
        $kazakh = $groupData[$index][7] === 'kk';
        for ($n = 0; $n < 10; $n++) {
            $female = mt_rand(0, 1) === 1;
            $last = ($kazakh ? $kkLast : $ruLast)[mt_rand(0, 9)];
            if ($female) {
                $last = $kazakh ? preg_replace('/(ов|ев)$/u', '$1а', $last) : $last . 'а';
            }
            $first = ($female ? ($kazakh ? $kkFirstF : $ruFirstF) : ($kazakh ? $kkFirst : $ruFirst))[mt_rand(0, 9)];
            $studentService->enroll([
                'last_name' => $last, 'first_name' => $first, 'middle_name' => null, 'email' => null, 'phone' => null,
                'login' => null, 'locale' => $kazakh ? 'kk' : 'ru', 'group_id' => $groupId, 'order_number' => '№ ' . (100 + $index) . '-К',
                'student_number' => null, 'iin' => null, 'birth_date' => sprintf('%d-%02d-%02d', $admission - 16, mt_rand(1, 12), mt_rand(1, 28)),
                'gender' => $female ? 'female' : 'male', 'enrollment_date' => $admission . '-09-01',
                'funding' => mt_rand(0, 3) === 0 ? 'paid' : 'state_grant',
            ]);
            $count++;
        }
    }
    $out("Demo: {$count} students enrolled.");

    Database::execute("INSERT INTO settings (`key`, `value`, `type`) VALUES ('demo_seeded', '1', 'bool') ON DUPLICATE KEY UPDATE `value` = '1'");
};
