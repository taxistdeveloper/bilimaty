<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use Tests\TestCase;

final class ValidatorTest extends TestCase
{
    /** Builds a valid IIN from 11 digits using the official check-digit algorithm. */
    private function iin(string $eleven): string
    {
        $d = array_map('intval', str_split($eleven));
        $sum = static fn (array $w): int => array_sum(array_map(static fn ($x, $y) => $x * $y, array_slice($d, 0, 11), $w)) % 11;
        $c = $sum([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);
        if ($c === 10) {
            $c = $sum([3, 4, 5, 6, 7, 8, 9, 10, 11, 1, 2]);
        }

        return $c === 10 ? '' : $eleven . $c;
    }

    private function errors(array $data, array $rules): array
    {
        return Validator::make($data, $rules)->errors();
    }

    public function testRequiredAndNullable(): void
    {
        $this->assertSame(['name'], array_keys($this->errors(['name' => '  '], ['name' => 'required|string'])));
        $v = Validator::make(['note' => ''], ['note' => 'nullable|string|max:5']);
        $this->assertSame([], $v->errors());
        $this->assertNull($v->validated()['note']);
    }

    public function testIin(): void
    {
        $valid = $this->iin('05010260001') ?: $this->iin('05010260002');
        $this->assertTrue(Validator::isValidIin($valid), "Generated IIN {$valid} must be valid");
        $broken = substr($valid, 0, 11) . (string) (((int) $valid[11] + 1) % 10);
        $this->assertFalse(Validator::isValidIin($broken), 'Wrong check digit must fail');
        $this->assertFalse(Validator::isValidIin('051302600011'), 'Month 13 must fail');
        $this->assertFalse(Validator::isValidIin('12345'), 'Short value must fail');
    }

    public function testDecimalRangeComparesNumbersNotLength(): void
    {
        // Regression (stage 8): "1.00" was rejected and "5" accepted because the length was compared.
        $rules = ['rate' => 'nullable|decimal:2|between:0.01,3'];
        $this->assertSame([], $this->errors(['rate' => '1.00'], $rules));
        $this->assertSame(['rate'], array_keys($this->errors(['rate' => '5'], $rules)));
        $this->assertSame(['rate'], array_keys($this->errors(['rate' => '1.234'], $rules)));
    }

    public function testDateTimeAfterIn(): void
    {
        $this->assertSame([], $this->errors(['d' => '2026-02-28'], ['d' => 'date']));
        $this->assertSame(['d'], array_keys($this->errors(['d' => '2026-02-30'], ['d' => 'date'])));
        $this->assertSame([], $this->errors(['t' => '08:30'], ['t' => 'time']));
        $this->assertSame(['t'], array_keys($this->errors(['t' => '25:99'], ['t' => 'time'])));
        $this->assertSame(['end'], array_keys($this->errors(['start' => '10:00', 'end' => '09:00'], ['end' => 'after:start'])));
        $this->assertSame(['x'], array_keys($this->errors(['x' => 'z'], ['x' => 'in:a,b'])));
    }

    public function testEveryRuleHasAMessageInBothLanguages(): void
    {
        $root = dirname(__DIR__, 2);
        $ru = require $root . '/app/Lang/ru.php';
        $kk = require $root . '/app/Lang/kk.php';
        $source = (string) file_get_contents($root . '/app/Core/Validator.php');
        // The rule names are the arms of the match() in check(), up to its default arm.
        $from = strpos($source, "'required', 'nullable' => true");
        $block = substr($source, (int) $from, (int) strpos($source, 'default => throw', (int) $from) - (int) $from);
        preg_match_all("/^\\s+'([a-z_]+)'(?:, '([a-z_]+)')* =>/m", $block, $m);
        $rules = array_diff(array_unique($m[1]), ['required', 'nullable']);
        $this->assertTrue(count($rules) > 15, 'Rules were not found in Validator.php');
        foreach ($rules as $rule) {
            $this->assertTrue(isset($ru['validation'][$rule], $kk['validation'][$rule]), "validation.{$rule} is missing in ru/kk");
        }
    }
}
