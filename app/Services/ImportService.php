<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Validator;
use App\Helpers\SpreadsheetReader;
use Throwable;

/**
 * Bulk import of students and teachers from Excel (.xlsx) or CSV.
 *
 * 1. preview(): reads the file, maps columns by their headings (Russian, Kazakh or the field key),
 *    normalises values (dates, gender, funding) and validates every row with the same rules as the
 *    manual forms, plus duplicates inside the file and the user's access scope.
 * 2. The checked rows are kept in storage/private/imports/<token>.json for one hour.
 * 3. commit(): creates the valid rows one by one (each in its own transaction, through the same
 *    services as the forms), returns logins and temporary passwords to hand out.
 */
final class ImportService
{
    public const TYPES = ['students', 'teachers'];
    private const TTL = 3600;

    /** field => accepted headings (lower case) */
    private const COLUMNS = [
        'students' => [
            'last_name' => ['фамилия', 'тегі', 'last_name'],
            'first_name' => ['имя', 'аты', 'first_name'],
            'middle_name' => ['отчество', 'әкесінің аты', 'middle_name'],
            'iin' => ['иин', 'жсн', 'iin'],
            'birth_date' => ['дата рождения', 'туған күні', 'birth_date'],
            'gender' => ['пол', 'жынысы', 'gender'],
            'group' => ['группа', 'топ', 'group'],
            'enrollment_date' => ['дата зачисления', 'қабылданған күні', 'enrollment_date'],
            'order_number' => ['номер приказа', 'приказ', 'бұйрық нөмірі', 'order_number'],
            'funding' => ['финансирование', 'қаржыландыру', 'funding'],
            'student_number' => ['номер студента', 'студент нөмірі', 'student_number'],
            'phone' => ['телефон', 'phone'],
            'email' => ['e-mail', 'email', 'эл. почта'],
            'locale' => ['язык', 'тіл', 'locale'],
        ],
        'teachers' => [
            'last_name' => ['фамилия', 'тегі', 'last_name'],
            'first_name' => ['имя', 'аты', 'first_name'],
            'middle_name' => ['отчество', 'әкесінің аты', 'middle_name'],
            'department' => ['отделение', 'бөлім', 'department'],
            'position' => ['должность', 'лауазымы', 'position'],
            'academic_degree' => ['учёная степень', 'ученая степень', 'ғылыми дәрежесі', 'academic_degree'],
            'employee_number' => ['табельный номер', 'табель нөмірі', 'employee_number'],
            'hire_date' => ['дата приёма', 'дата приема', 'жұмысқа қабылданған күні', 'hire_date'],
            'phone' => ['телефон', 'phone'],
            'email' => ['e-mail', 'email', 'эл. почта'],
            'locale' => ['язык', 'тіл', 'locale'],
        ],
    ];

    private const REQUIRED = [
        'students' => ['last_name', 'first_name', 'group'],
        'teachers' => ['last_name', 'first_name'],
    ];

    /** Column heading as shown to people: "дата рождения" → "Дата рождения". */
    public static function heading(string $name): string
    {
        return mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
    }

    /** @return array<string, list<string>> */
    public static function columns(string $type): array
    {
        return self::COLUMNS[$type];
    }

    /**
     * @return array{token: string, rows: list<array<string, mixed>>, unknown: list<string>}
     * @throws \App\Core\ValidationException when the file cannot be read or has no needed columns
     */
    public function preview(string $type, string $path, string $originalName): array
    {
        try {
            $table = SpreadsheetReader::read($path, $originalName);
        } catch (Throwable) {
            throw new \App\Core\ValidationException(['file' => [__('import.file_unreadable')]]);
        }
        $table = array_values(array_filter($table, static fn (array $r): bool => implode('', $r) !== ''));
        if (count($table) < 2) {
            throw new \App\Core\ValidationException(['file' => [__('import.file_empty')]]);
        }
        if (count($table) - 1 > SpreadsheetReader::MAX_ROWS) {
            throw new \App\Core\ValidationException(['file' => [__('import.too_many', ['max' => SpreadsheetReader::MAX_ROWS])]]);
        }

        // Map columns by heading.
        $map = [];
        $unknown = [];
        foreach ($table[0] as $index => $heading) {
            $h = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $heading) ?? ''), 'UTF-8');
            $h = rtrim($h, ' *:');
            $field = null;
            foreach (self::COLUMNS[$type] as $key => $names) {
                if (in_array($h, $names, true)) {
                    $field = $key;
                }
            }
            if ($field !== null) {
                $map[$index] = $field;
            } elseif ($h !== '') {
                $unknown[] = $heading;
            }
        }
        $missing = array_diff(self::REQUIRED[$type], $map);
        if ($missing !== []) {
            throw new \App\Core\ValidationException(['file' => [__('import.columns_missing', ['columns' => implode(', ', array_map(static fn (string $f): string => self::COLUMNS[$type][$f][0], $missing))])]]);
        }

        $rows = [];
        $seen = ['iin' => [], 'email' => [], 'student_number' => [], 'employee_number' => [], 'person' => []];
        foreach (array_slice($table, 1) as $i => $cells) {
            $data = [];
            foreach ($map as $index => $field) {
                $data[$field] = trim((string) ($cells[$index] ?? ''));
            }
            [$values, $errors] = $type === 'students' ? $this->student($data) : $this->teacher($data);
            foreach (['iin', 'email', 'student_number', 'employee_number'] as $unique) {
                $v = (string) ($values[$unique] ?? '');
                if ($v !== '') {
                    if (isset($seen[$unique][mb_strtolower($v)])) {
                        $errors[] = __('import.duplicate_in_file', ['field' => __('fields.' . $unique), 'row' => $seen[$unique][mb_strtolower($v)]]);
                    }
                    $seen[$unique][mb_strtolower($v)] ??= $i + 2;
                }
            }
            $rows[] = ['line' => $i + 2, 'source' => $data, 'values' => $values, 'errors' => array_values(array_unique($errors))];
        }

        $token = bin2hex(random_bytes(16));
        $this->store($token, ['type' => $type, 'user_id' => Auth::id(), 'created' => time(), 'rows' => $rows]);

        return ['token' => $token, 'rows' => $rows, 'unknown' => $unknown];
    }

    /** @return array<string, mixed>|null the stored preview of the same user, if not expired */
    public function load(string $token, string $type): ?array
    {
        $file = self::file($token);
        if ($file === null || !is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || $data['type'] !== $type || (int) $data['user_id'] !== (int) Auth::id() || time() - (int) $data['created'] > self::TTL) {
            return null;
        }

        return $data;
    }

    /**
     * Import the rows without errors. Rows are re-validated (the data may have changed since the preview).
     *
     * @return array{created: list<array<string, string>>, failed: list<array{line: int, errors: list<string>}>}
     */
    public function commit(string $token, string $type): array
    {
        $preview = $this->load($token, $type) ?? throw new \App\Core\ValidationException(['file' => [__('import.expired')]]);
        @unlink((string) self::file($token)); // one use only
        $created = [];
        $failed = [];
        foreach ($preview['rows'] as $row) {
            if ($row['errors'] !== []) {
                continue;
            }
            [$values, $errors] = $type === 'students' ? $this->student($row['source']) : $this->teacher($row['source']);
            if ($errors !== []) {
                $failed[] = ['line' => (int) $row['line'], 'errors' => $errors];
                continue;
            }
            try {
                $result = $type === 'students' ? (new StudentService())->enroll($values) : (new TeacherService())->create($values);
                $created[] = [
                    'name' => trim($values['last_name'] . ' ' . $values['first_name'] . ' ' . ($values['middle_name'] ?? '')),
                    'extra' => (string) ($type === 'students' ? ($row['source']['group'] ?? '') : ($row['source']['department'] ?? '')),
                    'login' => $result['login'],
                    'password' => $result['password'],
                ];
            } catch (\App\Core\ValidationException $e) {
                $failed[] = ['line' => (int) $row['line'], 'errors' => array_merge(...array_values($e->errors))];
            } catch (Throwable $e) {
                \App\Core\ErrorHandler::report($e);
                $failed[] = ['line' => (int) $row['line'], 'errors' => [__('import.row_failed')]];
            }
        }
        AuditLogger::log('import.' . $type, $type, null, null, ['created' => count($created), 'failed' => count($failed)]);

        return ['created' => $created, 'failed' => $failed];
    }

    // ---- rows ----------------------------------------------------------------

    /** @return array{0: array<string, mixed>, 1: list<string>} */
    private function student(array $d): array
    {
        $values = $this->person($d) + [
            'iin' => $d['iin'] ?? '',
            'birth_date' => SpreadsheetReader::date((string) ($d['birth_date'] ?? '')),
            'gender' => self::gender((string) ($d['gender'] ?? '')),
            'enrollment_date' => SpreadsheetReader::date((string) ($d['enrollment_date'] ?? '')),
            'order_number' => $d['order_number'] ?? '',
            'funding' => self::funding((string) ($d['funding'] ?? '')),
            'student_number' => $d['student_number'] ?? '',
        ];
        $errors = $this->validate($values, array_diff_key(\App\Validators\StudentValidator::rules(), ['login' => 1]) + ['order_number' => 'nullable|string|max:50']);
        $code = trim((string) ($d['group'] ?? ''));
        $group = $code !== '' ? Database::fetch("SELECT id, status FROM `groups` WHERE code = ?", [$code]) : null;
        if ($group === null) {
            $errors[] = __('import.group_not_found', ['group' => $code]);
        } elseif ($group['status'] !== 'active' || !AccessScope::allowsGroup((int) $group['id'])) {
            $errors[] = __('import.group_not_allowed', ['group' => $code]);
        } else {
            $values['group_id'] = (int) $group['id'];
        }
        foreach (['iin', 'birth_date', 'gender', 'enrollment_date', 'order_number', 'funding', 'student_number'] as $k) {
            $values[$k] = $values[$k] === '' ? null : $values[$k];
        }

        return [$values, $errors];
    }

    /** @return array{0: array<string, mixed>, 1: list<string>} */
    private function teacher(array $d): array
    {
        $values = $this->person($d) + [
            'position' => $d['position'] ?? '',
            'academic_degree' => $d['academic_degree'] ?? '',
            'employee_number' => $d['employee_number'] ?? '',
            'hire_date' => SpreadsheetReader::date((string) ($d['hire_date'] ?? '')),
            'status' => 'active',
            'department_id' => null,
        ];
        $errors = $this->validate($values, array_diff_key(\App\Validators\TeacherValidator::rules(), ['login' => 1, 'department_id' => 1]));
        $dep = trim((string) ($d['department'] ?? ''));
        if ($dep !== '') {
            $id = Database::fetchColumn('SELECT id FROM departments WHERE code = ? OR name_ru = ? OR name_kk = ? LIMIT 1', [$dep, $dep, $dep]);
            if ($id === null) {
                $errors[] = __('import.department_not_found', ['department' => $dep]);
            } elseif (!AccessScope::allowsDepartment((int) $id)) {
                $errors[] = __('import.department_not_allowed', ['department' => $dep]);
            } else {
                $values['department_id'] = (int) $id;
            }
        }
        foreach (['position', 'academic_degree', 'employee_number', 'hire_date'] as $k) {
            $values[$k] = $values[$k] === '' ? null : $values[$k];
        }

        return [$values, $errors];
    }

    /** @return array<string, mixed> */
    private function person(array $d): array
    {
        $locale = mb_strtolower(trim((string) ($d['locale'] ?? '')));

        return [
            'last_name' => self::name((string) ($d['last_name'] ?? '')),
            'first_name' => self::name((string) ($d['first_name'] ?? '')),
            'middle_name' => self::name((string) ($d['middle_name'] ?? '')) ?: null,
            'email' => ($e = mb_strtolower(trim((string) ($d['email'] ?? '')))) !== '' ? $e : null,
            'phone' => ($p = trim((string) ($d['phone'] ?? ''))) !== '' ? $p : null,
            'locale' => in_array($locale, ['ru', 'рус', 'русский', 'орыс'], true) ? 'ru' : 'kk',
        ];
    }

    /** @return list<string> */
    private function validate(array $values, array $rules): array
    {
        $out = [];
        foreach (Validator::make($values, $rules)->errors() as $messages) {
            $out = array_merge($out, $messages);
        }

        return $out;
    }

    private static function name(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? '' : mb_convert_case(mb_substr($value, 0, 1), MB_CASE_UPPER) . mb_substr($value, 1);
    }

    private static function gender(string $value): string
    {
        $v = mb_strtolower(trim($value));

        return match (true) {
            $v === '' => '',
            in_array($v, ['м', 'муж', 'мужской', 'male', 'm', 'ер', 'ер адам'], true) => 'male',
            in_array($v, ['ж', 'жен', 'женский', 'female', 'f', 'әйел', 'ә'], true) => 'female',
            default => $value, // fails validation with a clear message
        };
    }

    private static function funding(string $value): string
    {
        $v = mb_strtolower(trim($value));

        return match (true) {
            $v === '' => '',
            str_contains($v, 'грант') || str_contains($v, 'гос') || str_contains($v, 'мемлекет') || $v === 'state_grant' => 'state_grant',
            str_contains($v, 'плат') || str_contains($v, 'ақылы') || $v === 'paid' => 'paid',
            default => $value,
        };
    }

    // ---- storage -------------------------------------------------------------

    private function store(string $token, array $data): void
    {
        $dir = \App\Core\Tenant::storage('imports');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        foreach (glob($dir . '/*.json') ?: [] as $old) {
            if (filemtime($old) < time() - self::TTL) {
                @unlink($old); // expired previews
            }
        }
        file_put_contents($dir . '/' . $token . '.json', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private static function file(string $token): ?string
    {
        return preg_match('/^[a-f0-9]{32}$/', $token) ? \App\Core\Tenant::storage('imports') . '/' . $token . '.json' : null;
    }
}
