<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Surveys, questions, options, responses and the aggregated statistics.
 */
final class SurveyRepository
{
    private const COLUMNS = 's.*, d.name_ru AS department_name_ru, d.name_kk AS department_name_kk, g.code AS group_code,
        (SELECT COUNT(*) FROM survey_responses r WHERE r.survey_id = s.id) AS responses_count,
        (SELECT COUNT(*) FROM survey_questions q WHERE q.survey_id = s.id) AS questions_count';

    private const JOINS = ' FROM surveys s LEFT JOIN departments d ON d.id = s.department_id LEFT JOIN `groups` g ON g.id = s.group_id';

    // ---- surveys -------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function list(?string $status): array
    {
        $where = in_array($status, ['draft', 'published', 'closed'], true) ? ' WHERE s.status = ?' : '';

        return Database::fetchAll('SELECT ' . self::COLUMNS . self::JOINS . $where . ' ORDER BY s.status = \'published\' DESC, s.starts_on DESC, s.id DESC', $where !== '' ? [$status] : []);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE s.id = ?', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);
        Database::execute(
            'INSERT INTO surveys (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
            array_values($data)
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $allowed = ['title_ru', 'title_kk', 'description_ru', 'description_kk', 'audience', 'department_id', 'course', 'group_id', 'is_anonymous',
            'starts_on', 'ends_on', 'status', 'published_at', 'closed_at'];
        $data = array_intersect_key($data, array_flip($allowed));
        if ($data === []) {
            return;
        }
        $set = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($data)));
        Database::execute("UPDATE surveys SET {$set} WHERE id = ?", [...array_values($data), $id]);
    }

    public function delete(int $id): void
    {
        Database::execute('DELETE FROM surveys WHERE id = ?', [$id]);
    }

    // ---- questions -----------------------------------------------------------

    /** @return list<array<string, mixed>> questions with 'options' */
    public function questions(int $surveyId): array
    {
        $questions = Database::fetchAll('SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY sort_order, id', [$surveyId]);
        if ($questions === []) {
            return [];
        }
        $options = [];
        foreach (Database::fetchAll(
            'SELECT o.* FROM survey_options o JOIN survey_questions q ON q.id = o.question_id WHERE q.survey_id = ? ORDER BY o.sort_order, o.id',
            [$surveyId]
        ) as $o) {
            $options[(int) $o['question_id']][] = $o;
        }
        foreach ($questions as &$q) {
            $q['options'] = $options[(int) $q['id']] ?? [];
        }

        return $questions;
    }

    /** @return array<string, mixed>|null */
    public function question(int $id): ?array
    {
        $q = Database::fetch('SELECT * FROM survey_questions WHERE id = ?', [$id]);
        if ($q !== null) {
            $q['options'] = Database::fetchAll('SELECT * FROM survey_options WHERE question_id = ? ORDER BY sort_order, id', [$id]);
        }

        return $q;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{0: string, 1: ?string}> $options [label_ru, label_kk]
     */
    public function saveQuestion(int $surveyId, ?int $id, array $data, array $options): int
    {
        $columns = ['type', 'text_ru', 'text_kk', 'is_required', 'scale_min', 'scale_max', 'min_label_ru', 'min_label_kk', 'max_label_ru', 'max_label_kk'];
        $values = array_map(static fn (string $c) => $data[$c] ?? null, $columns);
        if ($id === null) {
            $order = (int) Database::fetchColumn('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM survey_questions WHERE survey_id = ?', [$surveyId]);
            Database::execute(
                'INSERT INTO survey_questions (survey_id, sort_order, ' . implode(', ', $columns) . ') VALUES (?, ?, ' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                [$surveyId, $order, ...$values]
            );
            $id = Database::lastInsertId();
        } else {
            Database::execute('UPDATE survey_questions SET ' . implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", $columns)) . ' WHERE id = ?', [...$values, $id]);
            Database::execute('DELETE FROM survey_options WHERE question_id = ?', [$id]);
        }
        foreach ($options as $i => [$ru, $kk]) {
            Database::execute('INSERT INTO survey_options (question_id, label_ru, label_kk, sort_order) VALUES (?, ?, ?, ?)', [$id, $ru, $kk, $i + 1]);
        }

        return $id;
    }

    public function deleteQuestion(int $id): void
    {
        Database::execute('DELETE FROM survey_questions WHERE id = ?', [$id]);
    }

    /** Swap the question with its neighbour (direction -1 = up, +1 = down). */
    public function moveQuestion(array $question, int $direction): void
    {
        $neighbour = Database::fetch(
            'SELECT id, sort_order FROM survey_questions WHERE survey_id = ? AND (sort_order ' . ($direction < 0 ? '<' : '>') . ' ? OR (sort_order = ? AND id ' . ($direction < 0 ? '<' : '>') . ' ?))
             ORDER BY sort_order ' . ($direction < 0 ? 'DESC' : 'ASC') . ', id ' . ($direction < 0 ? 'DESC' : 'ASC') . ' LIMIT 1',
            [(int) $question['survey_id'], (int) $question['sort_order'], (int) $question['sort_order'], (int) $question['id']]
        );
        if ($neighbour === null) {
            return;
        }
        // Renumber the whole list first so equal sort_order values cannot block a swap.
        $ids = array_map('intval', array_column(Database::fetchAll('SELECT id FROM survey_questions WHERE survey_id = ? ORDER BY sort_order, id', [(int) $question['survey_id']]), 'id'));
        $a = array_search((int) $question['id'], $ids, true);
        $b = array_search((int) $neighbour['id'], $ids, true);
        [$ids[$a], $ids[$b]] = [$ids[$b], $ids[$a]];
        foreach ($ids as $i => $qid) {
            Database::execute('UPDATE survey_questions SET sort_order = ? WHERE id = ?', [$i + 1, $qid]);
        }
    }

    // ---- respondents ---------------------------------------------------------

    /**
     * Who the user is as a respondent: student (group, course, department) or teacher (department).
     *
     * @return array{role: string, department_id: ?int, course: ?int, group_id: ?int}|null
     */
    public function respondent(int $userId): ?array
    {
        $student = Database::fetch(
            "SELECT s.current_group_id AS group_id, g.course, sp.department_id FROM students s
             LEFT JOIN `groups` g ON g.id = s.current_group_id LEFT JOIN specialties sp ON sp.id = g.specialty_id
             WHERE s.user_id = ? AND s.status IN ('active','academic_leave')",
            [$userId]
        );
        if ($student !== null) {
            return ['role' => 'student', 'department_id' => $student['department_id'] !== null ? (int) $student['department_id'] : null,
                'course' => $student['course'] !== null ? (int) $student['course'] : null, 'group_id' => $student['group_id'] !== null ? (int) $student['group_id'] : null];
        }
        $teacher = Database::fetch("SELECT department_id FROM teachers WHERE user_id = ? AND status <> 'dismissed'", [$userId]);

        return $teacher !== null ? ['role' => 'teacher', 'department_id' => $teacher['department_id'] !== null ? (int) $teacher['department_id'] : null, 'course' => null, 'group_id' => null] : null;
    }

    /** @return list<array<string, mixed>> published surveys of the period with an 'answered' flag */
    public function openSurveys(int $userId, string $today): array
    {
        return Database::fetchAll(
            "SELECT s.*, EXISTS(SELECT 1 FROM survey_participants p WHERE p.survey_id = s.id AND p.user_id = ?) AS answered
             FROM surveys s WHERE s.status = 'published' AND s.starts_on <= ? AND s.ends_on >= ? ORDER BY s.ends_on, s.id",
            [$userId, $today, $today]
        );
    }

    public function hasAnswered(int $surveyId, int $userId): bool
    {
        return Database::fetchColumn('SELECT 1 FROM survey_participants WHERE survey_id = ? AND user_id = ?', [$surveyId, $userId]) !== null;
    }

    /** Number of people the survey is addressed to (completion rate base). */
    public function audienceSize(array $survey): int
    {
        $count = 0;
        if (in_array($survey['audience'], ['students', 'all'], true)) {
            $where = ["s.status IN ('active','academic_leave')", 'u.is_active = 1', 'u.deleted_at IS NULL'];
            $params = [];
            foreach (['department_id' => 'sp.department_id', 'course' => 'g.course', 'group_id' => 's.current_group_id'] as $key => $column) {
                if ($survey[$key] !== null) {
                    $where[] = "{$column} = ?";
                    $params[] = (int) $survey[$key];
                }
            }
            $count += (int) Database::fetchColumn(
                'SELECT COUNT(*) FROM students s JOIN users u ON u.id = s.user_id LEFT JOIN `groups` g ON g.id = s.current_group_id
                 LEFT JOIN specialties sp ON sp.id = g.specialty_id WHERE ' . implode(' AND ', $where),
                $params
            );
        }
        if (in_array($survey['audience'], ['teachers', 'all'], true)) {
            $count += (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.status <> 'dismissed' AND u.is_active = 1 AND u.deleted_at IS NULL"
                    . ($survey['department_id'] !== null ? ' AND t.department_id = ?' : '')
                    . ' AND NOT EXISTS (SELECT 1 FROM students st WHERE st.user_id = u.id)',
                $survey['department_id'] !== null ? [(int) $survey['department_id']] : []
            );
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $response
     * @param list<array{question_id: int, option_id: ?int, value_int: ?int, value_text: ?string}> $answers
     */
    public function saveResponse(int $surveyId, ?int $participantUserId, array $response, array $answers): int
    {
        if ($participantUserId !== null) {
            // The primary key (survey_id, user_id) rejects a second answer from the same person.
            Database::execute('INSERT INTO survey_participants (survey_id, user_id, responded_on) VALUES (?, ?, CURDATE())', [$surveyId, $participantUserId]);
        }
        Database::execute(
            'INSERT INTO survey_responses (survey_id, user_id, respondent_role, department_id, course, group_id, submitted_on, submitted_at) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)',
            [$surveyId, $response['user_id'], $response['role'], $response['department_id'], $response['course'], $response['group_id'], $response['submitted_at']]
        );
        $responseId = Database::lastInsertId();
        foreach ($answers as $a) {
            Database::execute(
                'INSERT INTO survey_answers (response_id, question_id, option_id, value_int, value_text) VALUES (?, ?, ?, ?, ?)',
                [$responseId, $a['question_id'], $a['option_id'], $a['value_int'], $a['value_text']]
            );
        }

        return $responseId;
    }

    // ---- statistics ----------------------------------------------------------

    /**
     * Response ids of a segment (null = all).
     *
     * @return array{0: string, 1: list<mixed>} SQL condition on survey_responses r
     */
    private function segment(int $surveyId, ?string $column, mixed $value): array
    {
        $sql = 'r.survey_id = ?';
        $params = [$surveyId];
        if ($column !== null) {
            $sql .= $value === null ? " AND r.{$column} IS NULL" : " AND r.{$column} = ?";
            if ($value !== null) {
                $params[] = $value;
            }
        }

        return [$sql, $params];
    }

    public function responseCount(int $surveyId, ?string $column = null, mixed $value = null): int
    {
        [$where, $params] = $this->segment($surveyId, $column, $value);

        return (int) Database::fetchColumn("SELECT COUNT(*) FROM survey_responses r WHERE {$where}", $params);
    }

    /** @return array<int, int> option id => number of answers */
    public function optionCounts(int $surveyId, int $questionId, ?string $column = null, mixed $value = null): array
    {
        [$where, $params] = $this->segment($surveyId, $column, $value);
        $out = [];
        foreach (Database::fetchAll(
            "SELECT a.option_id, COUNT(*) AS n FROM survey_answers a JOIN survey_responses r ON r.id = a.response_id
             WHERE {$where} AND a.question_id = ? AND a.option_id IS NOT NULL GROUP BY a.option_id",
            [...$params, $questionId]
        ) as $row) {
            $out[(int) $row['option_id']] = (int) $row['n'];
        }

        return $out;
    }

    /** @return array<int, int> scale value => number of answers */
    public function scaleCounts(int $surveyId, int $questionId, ?string $column = null, mixed $value = null): array
    {
        [$where, $params] = $this->segment($surveyId, $column, $value);
        $out = [];
        foreach (Database::fetchAll(
            "SELECT a.value_int, COUNT(*) AS n FROM survey_answers a JOIN survey_responses r ON r.id = a.response_id
             WHERE {$where} AND a.question_id = ? AND a.value_int IS NOT NULL GROUP BY a.value_int",
            [...$params, $questionId]
        ) as $row) {
            $out[(int) $row['value_int']] = (int) $row['n'];
        }

        return $out;
    }

    /** Respondents who answered the question (for skipped optional questions). */
    public function answeredCount(int $surveyId, int $questionId): int
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(DISTINCT a.response_id) FROM survey_answers a JOIN survey_responses r ON r.id = a.response_id WHERE r.survey_id = ? AND a.question_id = ?',
            [$surveyId, $questionId]
        );
    }

    /** @return list<string> text answers, alphabetically (no order of submission is revealed) */
    public function texts(int $surveyId, int $questionId, int $limit = 200): array
    {
        return array_map('strval', array_column(Database::fetchAll(
            'SELECT a.value_text FROM survey_answers a JOIN survey_responses r ON r.id = a.response_id
             WHERE r.survey_id = ? AND a.question_id = ? AND a.value_text IS NOT NULL ORDER BY a.value_text LIMIT ' . max(1, $limit),
            [$surveyId, $questionId]
        ), 'value_text'));
    }

    /**
     * Segment values with the number of responses.
     *
     * @return list<array{value: mixed, n: int}>
     */
    public function segments(int $surveyId, string $column): array
    {
        $column = in_array($column, ['respondent_role', 'department_id', 'course', 'group_id'], true) ? $column : 'respondent_role';

        return array_map(
            static fn (array $r): array => ['value' => $r['v'], 'n' => (int) $r['n']],
            Database::fetchAll("SELECT r.{$column} AS v, COUNT(*) AS n FROM survey_responses r WHERE r.survey_id = ? GROUP BY r.{$column} ORDER BY r.{$column}", [$surveyId])
        );
    }

    /**
     * Raw answers for export: one row per response. Named surveys include the respondent.
     *
     * @return list<array<string, mixed>>
     */
    public function rawResponses(int $surveyId, bool $named): array
    {
        $rows = Database::fetchAll(
            'SELECT r.id, r.respondent_role, r.department_id, r.course, r.submitted_on'
                . ($named ? ', r.submitted_at, u.last_name, u.first_name, u.middle_name, g.code AS group_code' : '') . '
             FROM survey_responses r' . ($named ? ' LEFT JOIN users u ON u.id = r.user_id LEFT JOIN `groups` g ON g.id = r.group_id' : '') . '
             WHERE r.survey_id = ? ORDER BY ' . ($named ? 'r.id' : 'SHA2(CONCAT(r.id, ?), 256)'),
            $named ? [$surveyId] : [$surveyId, bin2hex(random_bytes(8))]
        );
        if ($rows === []) {
            return [];
        }
        $answers = [];
        foreach (Database::fetchAll(
            'SELECT a.response_id, a.question_id, a.option_id, a.value_int, a.value_text FROM survey_answers a JOIN survey_responses r ON r.id = a.response_id WHERE r.survey_id = ?',
            [$surveyId]
        ) as $a) {
            $answers[(int) $a['response_id']][(int) $a['question_id']][] = $a;
        }
        foreach ($rows as &$row) {
            $row['answers'] = $answers[(int) $row['id']] ?? [];
            if (!$named) {
                unset($row['id']); // the internal id is not exported for anonymous surveys
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> named surveys only: who answered */
    public function participants(int $surveyId): array
    {
        return Database::fetchAll(
            'SELECT u.last_name, u.first_name, u.middle_name, r.respondent_role, g.code AS group_code, r.submitted_at
             FROM survey_responses r JOIN users u ON u.id = r.user_id LEFT JOIN `groups` g ON g.id = r.group_id
             WHERE r.survey_id = ? ORDER BY u.last_name, u.first_name',
            [$surveyId]
        );
    }

    /** @return array<int, string> */
    public function labels(string $table, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === [] || !in_array($table, ['departments', 'groups'], true)) {
            return [];
        }
        $out = [];
        $sql = $table === 'groups'
            ? 'SELECT id, code AS label FROM `groups` WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
            : 'SELECT id, name_ru, name_kk FROM departments WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        foreach (Database::fetchAll($sql, $ids) as $r) {
            $out[(int) $r['id']] = $table === 'groups' ? (string) $r['label'] : localized($r);
        }

        return $out;
    }
}
