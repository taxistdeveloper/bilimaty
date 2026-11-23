<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\SurveyRepository;
use PDOException;

/**
 * Survey builder, answering and statistics.
 *
 * Anonymity: in anonymous surveys the response keeps neither the user nor the exact time; who has
 * answered is kept separately without any link to the answers (see the migration). Comparisons of
 * anonymous surveys show only segments with at least MIN_SEGMENT responses and never by group.
 */
final class SurveyService
{
    public const TYPES = ['single', 'multiple', 'text', 'scale'];
    public const SEGMENTS = ['respondent_role', 'department_id', 'course', 'group_id'];
    public const MIN_SEGMENT = 3;

    public function __construct(private readonly SurveyRepository $repository = new SurveyRepository())
    {
    }

    // ---- builder -------------------------------------------------------------

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $survey
     */
    public function save(array $input, ?array $survey): int
    {
        if ($survey !== null && $survey['status'] === 'closed') {
            throw new ValidationException(['title_ru' => [__('surveys.closed_readonly')]]);
        }
        $text = static fn (string $key, int $max): ?string => ($v = trim((string) ($input[$key] ?? ''))) !== '' ? mb_substr($v, 0, $max) : null;
        $data = [
            'title_ru' => $text('title_ru', 255),
            'title_kk' => $text('title_kk', 255),
            'description_ru' => $text('description_ru', 5000),
            'description_kk' => $text('description_kk', 5000),
            'starts_on' => (string) ($input['starts_on'] ?? ''),
            'ends_on' => (string) ($input['ends_on'] ?? ''),
        ];
        $published = $survey !== null && $survey['status'] === 'published';
        if ($published) {
            // A running survey keeps its audience, mode and start (the start field is not sent).
            $data['starts_on'] = (string) $survey['starts_on'];
        }
        $errors = [];
        if ($data['title_ru'] === null) {
            $errors['title_ru'][] = __('validation.required', ['field' => __('fields.title_ru')]);
        }
        foreach (['starts_on', 'ends_on'] as $d) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data[$d]) || strtotime($data[$d]) === false) {
                $errors[$d][] = __('validation.date', ['field' => __('fields.' . $d)]);
            }
        }
        if (!isset($errors['starts_on']) && !isset($errors['ends_on']) && $data['ends_on'] < $data['starts_on']) {
            $errors['ends_on'][] = __('surveys.period_invalid');
        }

        if ($published) {
            // Only texts and the end date change.
            if (!isset($errors['ends_on']) && $data['ends_on'] < date('Y-m-d')) {
                $errors['ends_on'][] = __('surveys.end_in_past');
            }
        } else {
            $audience = (string) ($input['audience'] ?? 'students');
            $data += [
                'audience' => in_array($audience, ['students', 'teachers', 'all'], true) ? $audience : 'students',
                'department_id' => ctype_digit((string) ($input['department_id'] ?? '')) ? (int) $input['department_id'] : null,
                'course' => ctype_digit((string) ($input['course'] ?? '')) ? (int) $input['course'] : null,
                'group_id' => ctype_digit((string) ($input['group_id'] ?? '')) ? (int) $input['group_id'] : null,
                'is_anonymous' => in_array($input['is_anonymous'] ?? null, ['1', 1, true, 'on'], true) ? 1 : 0,
            ];
            if ($data['department_id'] !== null && Database::fetchColumn('SELECT 1 FROM departments WHERE id = ?', [$data['department_id']]) === null) {
                $errors['department_id'][] = __('validation.exists', ['field' => __('fields.department_id')]);
            }
            if ($data['course'] !== null && ($data['course'] < 1 || $data['course'] > 6)) {
                $errors['course'][] = __('validation.between', ['field' => __('fields.course'), 'min' => 1, 'max' => 6]);
            }
            if ($data['group_id'] !== null) {
                $group = Database::fetch('SELECT g.course, sp.department_id FROM `groups` g JOIN specialties sp ON sp.id = g.specialty_id WHERE g.id = ?', [$data['group_id']]);
                if ($group === null) {
                    $errors['group_id'][] = __('validation.exists', ['field' => __('fields.group_id')]);
                } else {
                    // A group defines the department and course itself.
                    $data['department_id'] = (int) $group['department_id'];
                    $data['course'] = (int) $group['course'];
                }
            }
            if ($data['audience'] === 'teachers' && ($data['course'] !== null || $data['group_id'] !== null)) {
                $errors['course'][] = __('surveys.course_for_students');
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($survey === null) {
            $id = $this->repository->create($data + ['status' => 'draft', 'created_by' => Auth::id()]);
            AuditLogger::log('surveys.created', 'surveys', $id, null, $data);

            return $id;
        }
        $this->repository->update((int) $survey['id'], $data);
        AuditLogger::log('surveys.updated', 'surveys', (int) $survey['id'], array_intersect_key($survey, $data), $data);

        return (int) $survey['id'];
    }

    /**
     * @param array<string, mixed> $survey
     * @param array<string, mixed> $input type, text_ru, text_kk, is_required, options (lines "ru | kk"), scale_min/max, labels
     * @param array<string, mixed>|null $question
     */
    public function saveQuestion(array $survey, array $input, ?array $question): int
    {
        $this->ensureDraft($survey);
        $type = (string) ($input['type'] ?? '');
        $clean = static fn (string $key, int $max): ?string => ($v = trim((string) ($input[$key] ?? ''))) !== '' ? mb_substr($v, 0, $max) : null;
        $data = [
            'type' => $type,
            'text_ru' => $clean('text_ru', 500),
            'text_kk' => $clean('text_kk', 500),
            'is_required' => in_array($input['is_required'] ?? null, ['1', 1, true, 'on'], true) ? 1 : 0,
        ];
        $errors = [];
        if (!in_array($type, self::TYPES, true)) {
            $errors['type'][] = __('validation.in', ['field' => __('surveys.question_type'), 'values' => implode(', ', self::TYPES)]);
        }
        if ($data['text_ru'] === null) {
            $errors['text_ru'][] = __('validation.required', ['field' => __('fields.text_ru')]);
        }
        $options = [];
        if (in_array($type, ['single', 'multiple'], true)) {
            foreach (preg_split('/\R/u', (string) ($input['options'] ?? '')) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                [$ru, $kk] = array_map('trim', array_pad(explode('|', $line, 2), 2, ''));
                if ($ru === '' || mb_strlen($ru) > 255 || mb_strlen($kk) > 255) {
                    $errors['options'][] = __('surveys.option_invalid', ['option' => mb_substr($line, 0, 40)]);
                    continue;
                }
                $options[] = [$ru, $kk !== '' ? $kk : null];
            }
            $labels = array_map(static fn (array $o): string => mb_strtolower($o[0]), $options);
            if (count($options) < 2 || count($options) > 20) {
                $errors['options'][] = __('surveys.options_count');
            } elseif (count(array_unique($labels)) !== count($labels)) {
                $errors['options'][] = __('surveys.options_unique');
            }
        }
        if ($type === 'scale') {
            $min = (int) ($input['scale_min'] ?? 1);
            $max = (int) ($input['scale_max'] ?? 5);
            if ($min < 0 || $max > 10 || $min >= $max || $max - $min > 10) {
                $errors['scale_min'][] = __('surveys.scale_invalid');
            }
            $data += ['scale_min' => $min, 'scale_max' => $max, 'min_label_ru' => $clean('min_label_ru', 100), 'min_label_kk' => $clean('min_label_kk', 100),
                'max_label_ru' => $clean('max_label_ru', 100), 'max_label_kk' => $clean('max_label_kk', 100)];
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $id = Database::transaction(fn (): int => $this->repository->saveQuestion((int) $survey['id'], $question !== null ? (int) $question['id'] : null, $data, $options));
        AuditLogger::log($question === null ? 'surveys.question_created' : 'surveys.question_updated', 'survey_questions', $id, null, $data + ['options' => count($options)]);

        return $id;
    }

    /** @param array<string, mixed> $survey @param array<string, mixed> $question */
    public function deleteQuestion(array $survey, array $question): void
    {
        $this->ensureDraft($survey);
        $this->repository->deleteQuestion((int) $question['id']);
        AuditLogger::log('surveys.question_deleted', 'survey_questions', (int) $question['id'], ['text' => $question['text_ru']], null);
    }

    /** @param array<string, mixed> $survey @param array<string, mixed> $question */
    public function moveQuestion(array $survey, array $question, int $direction): void
    {
        $this->ensureDraft($survey);
        Database::transaction(fn () => $this->repository->moveQuestion($question, $direction));
    }

    /** @param array<string, mixed> $survey */
    public function publish(array $survey): void
    {
        $this->ensureDraft($survey);
        $questions = $this->repository->questions((int) $survey['id']);
        $errors = [];
        if ($questions === []) {
            $errors[] = __('surveys.no_questions');
        }
        foreach ($questions as $q) {
            if (in_array($q['type'], ['single', 'multiple'], true) && count($q['options']) < 2) {
                $errors[] = __('surveys.question_without_options', ['question' => mb_substr((string) $q['text_ru'], 0, 60)]);
            }
        }
        if ($survey['ends_on'] < date('Y-m-d')) {
            $errors[] = __('surveys.end_in_past');
        }
        if ($errors !== []) {
            throw new ValidationException(['status' => $errors]);
        }
        $this->repository->update((int) $survey['id'], ['status' => 'published', 'published_at' => date('Y-m-d H:i:s')]);
        AuditLogger::log('surveys.published', 'surveys', (int) $survey['id'], ['status' => 'draft'], ['status' => 'published']);
    }

    /** @param array<string, mixed> $survey */
    public function close(array $survey): void
    {
        if ($survey['status'] !== 'published') {
            throw new ValidationException(['status' => [__('surveys.wrong_status')]]);
        }
        $this->repository->update((int) $survey['id'], ['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s')]);
        AuditLogger::log('surveys.closed', 'surveys', (int) $survey['id'], ['status' => 'published'], ['status' => 'closed']);
    }

    /** @param array<string, mixed> $survey */
    public function delete(array $survey): void
    {
        if ($survey['status'] !== 'draft' || (int) $survey['responses_count'] > 0) {
            throw new ValidationException(['status' => [__('surveys.delete_only_draft')]]);
        }
        $this->repository->delete((int) $survey['id']);
        AuditLogger::log('surveys.deleted', 'surveys', (int) $survey['id'], ['title' => $survey['title_ru']], null);
    }

    /** Copy a survey (with questions) as a new draft — e.g. to repeat it next semester and compare. */
    public function duplicate(array $survey): int
    {
        return Database::transaction(function () use ($survey): int {
            $copy = array_intersect_key($survey, array_flip(['title_kk', 'description_ru', 'description_kk', 'audience', 'department_id', 'course', 'group_id', 'is_anonymous']));
            $id = $this->repository->create($copy + [
                'title_ru' => mb_substr((string) $survey['title_ru'], 0, 240) . ' ' . __('surveys.copy_suffix', [], 'ru'),
                'starts_on' => date('Y-m-d'), 'ends_on' => date('Y-m-d', strtotime('+14 days')), 'status' => 'draft', 'created_by' => Auth::id(),
            ]);
            foreach ($this->repository->questions((int) $survey['id']) as $q) {
                $this->repository->saveQuestion($id, null, $q, array_map(static fn (array $o): array => [$o['label_ru'], $o['label_kk']], $q['options']));
            }
            AuditLogger::log('surveys.duplicated', 'surveys', $id, null, ['source' => (int) $survey['id']]);

            return $id;
        });
    }

    // ---- answering -----------------------------------------------------------

    /**
     * Why the user cannot answer (null = can answer).
     *
     * @param array<string, mixed> $survey
     * @param array<string, mixed>|null $respondent
     */
    public function cannotAnswer(array $survey, int $userId, ?array $respondent): ?string
    {
        $today = date('Y-m-d');
        if ($survey['status'] !== 'published' || $survey['starts_on'] > $today || $survey['ends_on'] < $today) {
            return 'surveys.not_open';
        }
        if ($respondent === null || !$this->matches($survey, $respondent)) {
            return 'surveys.not_for_you';
        }
        if ($this->repository->hasAnswered((int) $survey['id'], $userId)) {
            return 'surveys.already_answered';
        }

        return null;
    }

    /** @param array<string, mixed> $survey @param array<string, mixed> $respondent */
    public function matches(array $survey, array $respondent): bool
    {
        $role = $respondent['role'];
        if (($survey['audience'] === 'students' && $role !== 'student') || ($survey['audience'] === 'teachers' && $role !== 'teacher')) {
            return false;
        }
        if ($survey['department_id'] !== null && (int) $survey['department_id'] !== $respondent['department_id']) {
            return false;
        }
        if ($survey['course'] !== null && ($role !== 'student' || (int) $survey['course'] !== $respondent['course'])) {
            return false;
        }

        return $survey['group_id'] === null || ($role === 'student' && (int) $survey['group_id'] === $respondent['group_id']);
    }

    /**
     * @param array<string, mixed> $survey
     * @param array<string, mixed> $respondent
     * @param array<int|string, mixed> $input question id => option id | option ids | value | text
     */
    public function respond(array $survey, int $userId, array $respondent, array $input): void
    {
        $reason = $this->cannotAnswer($survey, $userId, $respondent);
        if ($reason !== null) {
            throw new ValidationException(['answers' => [__($reason)]]);
        }
        $answers = [];
        $errors = [];
        foreach ($this->repository->questions((int) $survey['id']) as $q) {
            $qid = (int) $q['id'];
            $raw = $input[$qid] ?? null;
            $optionIds = array_map(static fn (array $o): int => (int) $o['id'], $q['options']);
            $given = false;
            switch ($q['type']) {
                case 'single':
                    if ($raw !== null && $raw !== '') {
                        $given = true;
                        if (!ctype_digit((string) $raw) || !in_array((int) $raw, $optionIds, true)) {
                            $errors['q' . $qid][] = __('surveys.answer_invalid');
                        } else {
                            $answers[] = ['question_id' => $qid, 'option_id' => (int) $raw, 'value_int' => null, 'value_text' => null];
                        }
                    }
                    break;
                case 'multiple':
                    $values = array_values(array_unique(array_filter(is_array($raw) ? $raw : [], static fn ($v): bool => ctype_digit((string) $v))));
                    if ($values !== []) {
                        $given = true;
                        foreach ($values as $v) {
                            if (!in_array((int) $v, $optionIds, true)) {
                                $errors['q' . $qid][] = __('surveys.answer_invalid');
                                break;
                            }
                            $answers[] = ['question_id' => $qid, 'option_id' => (int) $v, 'value_int' => null, 'value_text' => null];
                        }
                    }
                    break;
                case 'scale':
                    if ($raw !== null && $raw !== '') {
                        $given = true;
                        if (!ctype_digit((string) $raw) || (int) $raw < (int) $q['scale_min'] || (int) $raw > (int) $q['scale_max']) {
                            $errors['q' . $qid][] = __('surveys.answer_invalid');
                        } else {
                            $answers[] = ['question_id' => $qid, 'option_id' => null, 'value_int' => (int) $raw, 'value_text' => null];
                        }
                    }
                    break;
                default:
                    $text = trim((string) (is_string($raw) ? $raw : ''));
                    if ($text !== '') {
                        $given = true;
                        if (mb_strlen($text) > 2000) {
                            $errors['q' . $qid][] = __('surveys.text_too_long');
                        } else {
                            $answers[] = ['question_id' => $qid, 'option_id' => null, 'value_int' => null, 'value_text' => $text];
                        }
                    }
            }
            if (!$given && (bool) $q['is_required']) {
                $errors['q' . $qid][] = __('surveys.answer_required');
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $anonymous = (bool) $survey['is_anonymous'];
        try {
            Database::transaction(fn (): int => $this->repository->saveResponse((int) $survey['id'], $userId, [
                'user_id' => $anonymous ? null : $userId,
                'role' => $respondent['role'],
                'department_id' => $respondent['department_id'],
                'course' => $respondent['course'],
                'group_id' => $anonymous ? null : $respondent['group_id'],
                'submitted_at' => $anonymous ? null : date('Y-m-d H:i:s'),
            ], $answers));
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // second answer of the same person (parallel submit)
                throw new ValidationException(['answers' => [__('surveys.already_answered')]]);
            }
            throw $e;
        }
        // The audit log stores only the fact for anonymous surveys, never who answered.
        AuditLogger::log('surveys.answered', 'surveys', (int) $survey['id'], null, $anonymous ? ['anonymous' => true] : ['user_id' => $userId]);
    }

    // ---- statistics ----------------------------------------------------------

    /**
     * @param array<string, mixed> $survey
     * @return array{responses: int, audience: int, rate: ?float, questions: list<array<string, mixed>>}
     */
    public function results(array $survey): array
    {
        $id = (int) $survey['id'];
        $responses = $this->repository->responseCount($id);
        $audience = $this->repository->audienceSize($survey);
        $questions = [];
        foreach ($this->repository->questions($id) as $q) {
            $answered = $this->repository->answeredCount($id, (int) $q['id']);
            $q['answered'] = $answered;
            if (in_array($q['type'], ['single', 'multiple'], true)) {
                $counts = $this->repository->optionCounts($id, (int) $q['id']);
                foreach ($q['options'] as &$o) {
                    $o['count'] = $counts[(int) $o['id']] ?? 0;
                    $o['percent'] = $answered > 0 ? round($o['count'] / $answered * 100, 1) : 0.0;
                }
                unset($o);
            } elseif ($q['type'] === 'scale') {
                $counts = $this->repository->scaleCounts($id, (int) $q['id']);
                $q['distribution'] = [];
                for ($v = (int) $q['scale_min']; $v <= (int) $q['scale_max']; $v++) {
                    $q['distribution'][$v] = $counts[$v] ?? 0;
                }
                $total = array_sum($counts);
                $q['average'] = $total > 0 ? round(array_sum(array_map(static fn (int $v, int $n): int => $v * $n, array_keys($counts), $counts)) / $total, 2) : null;
            } else {
                $q['texts'] = $this->repository->texts($id, (int) $q['id']);
            }
            $questions[] = $q;
        }

        return ['responses' => $responses, 'audience' => $audience, 'rate' => $audience > 0 ? round(min(100, $responses / $audience * 100), 1) : null, 'questions' => $questions];
    }

    /**
     * Compare segments of respondents: averages of scales and shares of options.
     *
     * @param array<string, mixed> $survey
     * @return array{column: string, segments: list<array<string, mixed>>, hidden: int, questions: list<array<string, mixed>>}
     */
    public function compare(array $survey, string $column): array
    {
        $anonymous = (bool) $survey['is_anonymous'];
        $column = in_array($column, self::SEGMENTS, true) && !($anonymous && $column === 'group_id') ? $column : 'respondent_role';
        $min = $anonymous ? self::MIN_SEGMENT : 1;
        $id = (int) $survey['id'];
        $segments = [];
        $hidden = 0;
        $all = $this->repository->segments($id, $column);
        $labels = match ($column) {
            'department_id' => $this->repository->labels('departments', array_column($all, 'value')),
            'group_id' => $this->repository->labels('groups', array_column($all, 'value')),
            default => [],
        };
        foreach ($all as $s) {
            if ($s['n'] < $min) {
                $hidden += $s['n']; // too small to keep anonymity
                continue;
            }
            $value = $s['value'];
            $segments[] = ['value' => $value, 'n' => $s['n'], 'label' => match ($column) {
                'respondent_role' => __('surveys.roles.' . $value),
                'course' => $value !== null ? __('groups.course_n', ['n' => $value]) : __('surveys.no_value'),
                default => $value !== null ? ($labels[(int) $value] ?? '—') : __('surveys.no_value'),
            }];
        }
        $questions = [];
        foreach ($this->repository->questions($id) as $q) {
            if ($q['type'] === 'text') {
                continue;
            }
            $rows = [];
            foreach ($segments as $s) {
                if ($q['type'] === 'scale') {
                    $counts = $this->repository->scaleCounts($id, (int) $q['id'], $column, $s['value']);
                    $total = array_sum($counts);
                    $rows[] = ['average' => $total > 0 ? round(array_sum(array_map(static fn (int $v, int $n): int => $v * $n, array_keys($counts), $counts)) / $total, 2) : null, 'n' => $total];
                } else {
                    $counts = $this->repository->optionCounts($id, (int) $q['id'], $column, $s['value']);
                    $rows[] = ['percents' => array_map(static fn (array $o): float => $s['n'] > 0 ? round(($counts[(int) $o['id']] ?? 0) / $s['n'] * 100, 1) : 0.0, $q['options'])];
                }
            }
            $q['rows'] = $rows;
            $questions[] = $q;
        }

        return ['column' => $column, 'segments' => $segments, 'hidden' => $hidden, 'questions' => $questions];
    }

    /** @param array<string, mixed> $survey */
    private function ensureDraft(array $survey): void
    {
        if ($survey['status'] !== 'draft') {
            throw new ValidationException(['status' => [__('surveys.draft_only')]]);
        }
    }
}
