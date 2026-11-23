<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Server-side validation with pipe-delimited rules:
 *   ['login' => 'required|string|max:190', 'course' => 'required|integer|min:1|max:4']
 *
 * Supported: required, nullable, string, integer, numeric, decimal, boolean, email,
 * date, time, after:field, min, max, between, in, confirmed, alpha_dash, array, phone, password, iin,
 * unique:table,column[,ignoreId], exists:table,column (arrays: every element must exist).
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     */
    public function __construct(private readonly array $data, private readonly array $rules)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     */
    public static function make(array $data, array $rules): self
    {
        $validator = new self($data, $rules);
        $validator->run();

        return $validator;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> Only the fields that have rules. */
    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;
            // Passwords are validated exactly as typed: surrounding spaces are significant.
            if (is_string($value) && !str_contains($field, 'password')) {
                $value = trim($value);
            }

            $isEmpty = $value === null || $value === '' || $value === [];
            if ($isEmpty) {
                if (in_array('required', $rules, true)) {
                    $this->addError($field, 'required');
                } elseif (in_array('nullable', $rules, true) || array_key_exists($field, $this->data)) {
                    $this->validated[$field] = null;
                }
                continue;
            }

            // Rule names without parameters ("decimal:2" is numeric too): size rules then compare values.
            $names = array_map(static fn (string $r): string => explode(':', $r, 2)[0], $rules);
            $isNumeric = array_intersect(['integer', 'numeric', 'decimal'], $names) !== [];

            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                if (!$this->check($name, $param, $value, $field, $isNumeric)) {
                    $this->addError($field, $name, $this->replacements($name, $param));
                    break; // one message per field is enough for the UI
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function check(string $rule, ?string $param, mixed $value, string $field, bool $isNumeric): bool
    {
        return match ($rule) {
            'required', 'nullable' => true,
            'string' => is_string($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric' => is_numeric($value),
            'decimal' => is_string($value) || is_int($value) || is_float($value)
                ? (bool) preg_match('/^-?\d+(\.\d{1,' . (int) ($param ?? 2) . '})?$/', (string) $value)
                : false,
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1', 'on', 'off'], true),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'date' => is_string($value) && self::isDate($value),
            'alpha_dash' => is_string($value) && (bool) preg_match('/^[\pL\pN._-]+$/u', $value),
            'min' => $this->size($value, $isNumeric) >= (float) $param,
            'max' => $this->size($value, $isNumeric) <= (float) $param,
            'between' => (static function (float $size, string $p): bool {
                [$min, $max] = array_map('floatval', explode(',', $p, 2));

                return $size >= $min && $size <= $max;
            })($this->size($value, $isNumeric), (string) $param),
            'in' => in_array((string) $value, explode(',', (string) $param), true),
            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value,
            'array' => is_array($value),
            'phone' => is_string($value) && (bool) preg_match('/^\+?[0-9][0-9 ()\-]{6,19}$/', $value),
            // Password policy: at least 10 characters with letters and digits.
            'password' => is_string($value) && mb_strlen($value) >= 10
                && (bool) preg_match('/\pL/u', $value) && (bool) preg_match('/\d/', $value),
            'iin' => is_string($value) && self::isValidIin($value),
            'time' => is_string($value) && (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value),
            // after:other_field — string comparison, works for Y-m-d dates and HH:MM times.
            'after' => is_scalar($value) && (string) $value > (string) ($this->data[(string) $param] ?? ''),
            'unique' => $this->isUnique((string) $param, $value),
            'exists' => $this->exists((string) $param, $value),
            default => throw new \InvalidArgumentException("Unknown validation rule [{$rule}]."),
        };
    }

    /**
     * Kazakhstan IIN: 12 digits, valid birth date in the first 6 digits and the official
     * check digit (weights 1..11, fallback weights 3..11,1,2; remainder 10 = invalid).
     */
    public static function isValidIin(string $iin): bool
    {
        if (!preg_match('/^\d{12}$/', $iin)) {
            return false;
        }
        $month = (int) substr($iin, 2, 2);
        $day = (int) substr($iin, 4, 2);
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return false;
        }

        $digits = array_map('intval', str_split($iin));
        $checksum = static function (array $weights) use ($digits): int {
            $sum = 0;
            foreach ($weights as $i => $weight) {
                $sum += $digits[$i] * $weight;
            }

            return $sum % 11;
        };
        $control = $checksum([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);
        if ($control === 10) {
            $control = $checksum([3, 4, 5, 6, 7, 8, 9, 10, 11, 1, 2]);
        }

        return $control !== 10 && $control === $digits[11];
    }

    /** unique:table,column[,ignoreId] — ignores soft-deleted rows only when the caller says so. */
    private function isUnique(string $param, mixed $value): bool
    {
        [$table, $column, $ignoreId] = array_pad(explode(',', $param, 3), 3, null);
        $sql = sprintf(
            'SELECT COUNT(*) FROM %s WHERE %s = ?',
            Database::identifier((string) $table),
            Database::identifier((string) $column)
        );
        $params = [$value];
        if ($ignoreId !== null && $ignoreId !== '') {
            $sql .= ' AND id <> ?';
            $params[] = (int) $ignoreId;
        }

        return (int) Database::fetchColumn($sql, $params) === 0;
    }

    /** exists:table,column — for arrays every (distinct) element must exist. */
    private function exists(string $param, mixed $value): bool
    {
        [$table, $column] = array_pad(explode(',', $param, 2), 2, 'id');
        $values = array_values(array_unique(array_map('strval', is_array($value) ? $value : [$value])));
        if ($values === [] || array_filter($values, static fn (string $v): bool => $v === '') !== []) {
            return false;
        }
        $sql = sprintf(
            'SELECT COUNT(DISTINCT %2$s) FROM %1$s WHERE %2$s IN (%3$s)',
            Database::identifier($table),
            Database::identifier((string) $column),
            implode(', ', array_fill(0, count($values), '?'))
        );

        return (int) Database::fetchColumn($sql, $values) === count($values);
    }

    private function size(mixed $value, bool $isNumeric): float
    {
        if ($isNumeric && is_numeric($value)) {
            return (float) $value;
        }
        if (is_array($value)) {
            return count($value);
        }

        return mb_strlen((string) $value);
    }

    private static function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** @return array<string, string> */
    private function replacements(string $rule, ?string $param): array
    {
        return match ($rule) {
            'min', 'max' => [$rule => (string) $param],
            'between' => (static function (string $p): array {
                [$min, $max] = array_pad(explode(',', $p, 2), 2, '');

                return ['min' => $min, 'max' => $max];
            })((string) $param),
            'in' => ['values' => str_replace(',', ', ', (string) $param)],
            'after' => ['other' => (static fn (string $f): string => ($l = Translator::get('fields.' . $f)) === 'fields.' . $f ? $f : $l)((string) $param)],
            default => [],
        };
    }

    /** @param array<string, string> $replace */
    private function addError(string $field, string $rule, array $replace = []): void
    {
        $label = Translator::get('fields.' . $field);
        if ($label === 'fields.' . $field) {
            $label = $field;
        }
        $this->errors[$field][] = Translator::get('validation.' . $rule, ['field' => $label] + $replace);
    }
}
