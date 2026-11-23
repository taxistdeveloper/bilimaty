<?php

declare(strict_types=1);

namespace App\Helpers;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Reads the first sheet of an Excel .xlsx file (or a .csv) into rows of strings.
 * Uses only bundled PHP extensions (zip, xmlreader): no third-party library is needed.
 * Guards against oversized and "zip bomb" files.
 */
final class SpreadsheetReader
{
    public const MAX_ROWS = 2000;
    private const MAX_XML_BYTES = 40 * 1024 * 1024;

    /** @return list<list<string>> */
    public static function read(string $path, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($extension) {
            'xlsx' => self::xlsx($path),
            'csv', 'txt' => self::csv($path),
            default => throw new RuntimeException('unsupported'),
        };
    }

    /** @return list<list<string>> */
    private static function csv(string $path): array
    {
        $text = (string) file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1251'); // Excel "CSV" saved on Russian Windows
        }
        $firstLine = strtok($text, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $text);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count($rows) > self::MAX_ROWS) {
                break;
            }
            $rows[] = array_map(static fn ($v): string => trim((string) $v), $row);
        }
        fclose($handle);

        return $rows;
    }

    /** @return list<list<string>> */
    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('not_xlsx');
        }
        try {
            $sheet = self::firstSheetPath($zip);
            foreach (['xl/sharedStrings.xml', $sheet] as $name) {
                $stat = $zip->statName($name);
                if ($stat !== false && $stat['size'] > self::MAX_XML_BYTES) {
                    throw new RuntimeException('too_big');
                }
            }
            $shared = self::sharedStrings((string) $zip->getFromName('xl/sharedStrings.xml'));
            $xml = $zip->getFromName($sheet);
            if ($xml === false) {
                throw new RuntimeException('not_xlsx');
            }
        } finally {
            $zip->close();
        }

        $reader = new XMLReader();
        $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        $rows = [];
        $row = [];
        $col = 0;
        $type = '';
        $value = null;
        $inline = '';
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                switch ($reader->localName) {
                    case 'row':
                        $row = [];
                        break;
                    case 'c':
                        $col = self::columnIndex((string) $reader->getAttribute('r'), count($row));
                        $type = (string) $reader->getAttribute('t');
                        $value = null;
                        $inline = '';
                        break;
                    case 'v':
                        $value = $reader->readString();
                        break;
                    case 't':
                        if ($type === 'inlineStr') {
                            $inline .= $reader->readString();
                        }
                        break;
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                if ($reader->localName === 'c') {
                    $text = match ($type) {
                        's' => $shared[(int) $value] ?? '',
                        'inlineStr' => $inline,
                        'b' => $value === '1' ? '1' : '0',
                        default => (string) $value,
                    };
                    while (count($row) < $col) {
                        $row[] = '';
                    }
                    $row[$col] = trim($text);
                } elseif ($reader->localName === 'row') {
                    $rows[] = array_values($row);
                    if (count($rows) > self::MAX_ROWS) {
                        break;
                    }
                }
            }
        }
        $reader->close();

        return $rows;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $rels = (string) $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook !== '' && $rels !== ''
            && preg_match('/<(?:\w+:)?sheet\b[^>]*\br:id="([^"]+)"/', $workbook, $m)
            && preg_match('/<Relationship\b[^>]*Id="' . preg_quote($m[1], '/') . '"[^>]*Target="([^"]+)"/', $rels, $t)) {
            $target = ltrim($t[1], '/');

            return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** @return list<string> */
    private static function sharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }
        $reader = new XMLReader();
        $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        $out = [];
        $current = null;
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $current = '';
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't' && $current !== null) {
                $current .= $reader->readString();
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $out[] = (string) $current;
                $current = null;
            }
        }
        $reader->close();

        return $out;
    }

    /** "C7" → 2 (zero based); without a reference the next column is used. */
    private static function columnIndex(string $ref, int $next): int
    {
        if (!preg_match('/^([A-Z]+)/', $ref, $m)) {
            return $next;
        }
        $n = 0;
        foreach (str_split($m[1]) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    /** Excel serial date (e.g. 45901) → Y-m-d; other values are returned as they are. */
    public static function date(string $value): string
    {
        if (preg_match('/^\d{4,5}(\.\d+)?$/', $value) && (float) $value > 20000 && (float) $value < 80000) {
            return gmdate('Y-m-d', (int) round(((float) $value - 25569) * 86400));
        }
        if (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]); // 01.09.2026
        }

        return $value;
    }
}
