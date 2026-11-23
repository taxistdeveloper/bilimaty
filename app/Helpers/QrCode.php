<?php

declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

/**
 * Minimal QR Code encoder (ISO/IEC 18004): byte mode, error correction level M,
 * versions 1–10 (up to 213 bytes — enough for a verification URL). Output: SVG.
 * No external dependencies; structure follows the reference algorithm
 * (function patterns → Reed–Solomon ECC → interleaving → zig-zag placement → best mask).
 */
final class QrCode
{
    /** version => [data codewords, EC codewords per block, [[blocks, data codewords per block], …]] (level M) */
    private const VERSIONS = [
        1 => [16, 10, [[1, 16]]],
        2 => [28, 16, [[1, 28]]],
        3 => [44, 26, [[1, 44]]],
        4 => [64, 18, [[2, 32]]],
        5 => [86, 24, [[2, 43]]],
        6 => [108, 16, [[4, 27]]],
        7 => [124, 18, [[4, 31]]],
        8 => [154, 22, [[2, 38], [2, 39]]],
        9 => [182, 22, [[3, 36], [2, 37]]],
        10 => [216, 26, [[4, 43], [1, 44]]],
    ];

    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /** @var list<list<bool>> */
    private array $modules = [];

    /** @var list<list<bool>> */
    private array $isFunction = [];

    private int $size;

    /** @var list<int> */
    private static array $exp = [];

    /** @var list<int> */
    private static array $log = [];

    private function __construct(private readonly int $version)
    {
        $this->size = 17 + 4 * $version;
        $row = array_fill(0, $this->size, false);
        $this->modules = array_fill(0, $this->size, $row);
        $this->isFunction = array_fill(0, $this->size, $row);
    }

    /** @return list<list<bool>> dark modules, [row][column] */
    public static function matrix(string $data): array
    {
        $version = null;
        foreach (self::VERSIONS as $v => [$dataCodewords]) {
            $countBits = $v <= 9 ? 8 : 16;
            if (4 + $countBits + 8 * strlen($data) <= $dataCodewords * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new InvalidArgumentException('Data too long for QR versions 1–10 (level M).');
        }

        $qr = new self($version);
        $qr->drawFunctionPatterns();
        $qr->drawCodewords($qr->addEccAndInterleave($qr->encodeData($data)));

        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $qr->applyMask($mask);
            $qr->drawFormatBits($mask);
            $penalty = $qr->penalty();
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
            }
            $qr->applyMask($mask); // XOR again = undo
        }
        $qr->applyMask($bestMask);
        $qr->drawFormatBits($bestMask);

        return $qr->modules;
    }

    /** SVG image of the code with a quiet zone of $margin modules. */
    public static function svg(string $data, int $margin = 4, string $label = 'QR'): string
    {
        $matrix = self::matrix($data);
        $size = count($matrix);
        $path = '';
        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M' . ($x + $margin) . ',' . ($y + $margin) . 'h1v1h-1z';
                }
            }
        }
        $full = $size + 2 * $margin;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $full . ' ' . $full . '" shape-rendering="crispEdges" role="img" aria-label="'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"><rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="' . $path . '"/></svg>';
    }

    // ------------------------------------------------------------------ data

    /** @return list<int> data codewords incl. padding */
    private function encodeData(string $data): array
    {
        [$capacity] = self::VERSIONS[$this->version];
        $bits = [];
        $push = static function (int $value, int $length) use (&$bits): void {
            for ($i = $length - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };
        $push(0b0100, 4); // byte mode
        $push(strlen($data), $this->version <= 9 ? 8 : 16);
        foreach (str_split($data) as $char) {
            $push(ord($char), 8);
        }
        $capacityBits = $capacity * 8;
        $push(0, min(4, $capacityBits - count($bits))); // terminator
        $push(0, (8 - count($bits) % 8) % 8);           // byte alignment
        for ($pad = 0xEC; count($bits) < $capacityBits; $pad ^= 0xEC ^ 0x11) {
            $push($pad, 8);
        }

        $codewords = [];
        foreach (array_chunk($bits, 8) as $byte) {
            $codewords[] = (int) bindec(implode('', $byte));
        }

        return $codewords;
    }

    /** @param list<int> $data @return list<int> */
    private function addEccAndInterleave(array $data): array
    {
        [, $ecLength, $groups] = self::VERSIONS[$this->version];
        $blocks = [];
        $offset = 0;
        foreach ($groups as [$count, $length]) {
            for ($i = 0; $i < $count; $i++) {
                $chunk = array_slice($data, $offset, $length);
                $offset += $length;
                $blocks[] = [$chunk, self::reedSolomon($chunk, $ecLength)];
            }
        }
        $result = [];
        $maxData = max(array_map(static fn (array $b): int => count($b[0]), $blocks));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($blocks as [$chunk]) {
                if ($i < count($chunk)) {
                    $result[] = $chunk[$i];
                }
            }
        }
        for ($i = 0; $i < $ecLength; $i++) {
            foreach ($blocks as [, $ecc]) {
                $result[] = $ecc[$i];
            }
        }

        return $result;
    }

    /** @param list<int> $data @return list<int> EC codewords */
    private static function reedSolomon(array $data, int $degree): array
    {
        self::initGalois();
        $generator = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $j => $coefficient) {
                $next[$j] ^= $coefficient;
                $next[$j + 1] ^= self::multiply($coefficient, self::$exp[$i]);
            }
            $generator = $next;
        }
        $message = array_merge($data, array_fill(0, $degree, 0));
        $count = count($data);
        for ($i = 0; $i < $count; $i++) {
            $factor = $message[$i];
            if ($factor === 0) {
                continue;
            }
            foreach ($generator as $j => $coefficient) {
                $message[$i + $j] ^= self::multiply($coefficient, $factor);
            }
        }

        return array_slice($message, $count);
    }

    private static function initGalois(): void
    {
        if (self::$exp !== []) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function multiply(int $a, int $b): int
    {
        return $a === 0 || $b === 0 ? 0 : self::$exp[self::$log[$a] + self::$log[$b]];
    }

    // ------------------------------------------------------------------ layout ([y][x] = [row][column])

    private function setFunction(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$this->size - 4, 3], [3, $this->size - 4]] as [$x, $y]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $xx = $x + $dx;
                    $yy = $y + $dy;
                    if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                        $distance = max(abs($dx), abs($dy));
                        $this->setFunction($xx, $yy, $distance !== 2 && $distance !== 4);
                    }
                }
            }
        }
        $positions = self::ALIGNMENT[$this->version];
        $last = count($positions) - 1;
        foreach ($positions as $i => $px) {
            foreach ($positions as $j => $py) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->setFunction($px + $dx, $py + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->drawFormatBits(0); // reserve the area
        if ($this->version >= 7) {
            $remainder = $this->version;
            for ($i = 0; $i < 12; $i++) {
                $remainder = ($remainder << 1) ^ (($remainder >> 11) * 0x1F25);
            }
            $bits = $this->version << 12 | $remainder;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $this->size - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->setFunction($a, $b, $bit);
                $this->setFunction($b, $a, $bit);
            }
        }
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (0 << 3) | $mask; // level M = 0b00
        $remainder = $data;
        for ($i = 0; $i < 10; $i++) {
            $remainder = ($remainder << 1) ^ (($remainder >> 9) * 0x537);
        }
        $bits = ($data << 10 | $remainder) ^ 0x5412;
        $bit = static fn (int $i): bool => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, $bit($i));
        }
        $this->setFunction(8, 7, $bit(6));
        $this->setFunction(8, 8, $bit(7));
        $this->setFunction(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($this->size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $this->size - 15 + $i, $bit($i));
        }
        $this->setFunction(8, $this->size - 8, true); // dark module
    }

    /** @param list<int> $codewords */
    private function drawCodewords(array $codewords): void
    {
        $total = count($codewords) * 8;
        $i = 0;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => ((($x + $y) % 2) + ($x * $y) % 3) % 2 === 0,
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** Standard penalty rules N1–N4 (used only to pick the most readable mask). */
    private function penalty(): int
    {
        $n = $this->size;
        $m = $this->modules;
        $score = 0;
        $lines = [];
        for ($i = 0; $i < $n; $i++) {
            $lines[] = $m[$i];
            $lines[] = array_column($m, $i);
        }
        $pattern1 = [true, false, true, true, true, false, true, false, false, false, false];
        $pattern2 = array_reverse($pattern1);
        foreach ($lines as $line) {
            $run = 1;
            for ($i = 1; $i <= $n; $i++) {
                if ($i < $n && $line[$i] === $line[$i - 1]) {
                    $run++;
                    continue;
                }
                if ($run >= 5) {
                    $score += 3 + $run - 5;
                }
                $run = 1;
            }
            for ($i = 0; $i + 11 <= $n; $i++) {
                $slice = array_slice($line, $i, 11);
                if ($slice === $pattern1 || $slice === $pattern2) {
                    $score += 40;
                }
            }
        }
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $dark = 0;
        foreach ($m as $row) {
            $dark += count(array_filter($row));
        }
        $score += intdiv(abs($dark * 20 - $n * $n * 10), $n * $n) * 10;

        return $score;
    }
}
