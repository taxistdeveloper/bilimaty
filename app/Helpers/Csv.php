<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Response;

/**
 * CSV download that Excel opens directly: UTF-8 with BOM, ";" separator, formula cells neutralised.
 */
final class Csv
{
    /** @param list<list<scalar|null>> $rows */
    public static function download(array $rows, string $filename): Response
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            // Neutralise spreadsheet formulas in text cells (CSV injection).
            fputcsv($out, array_map(static fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $row), ';', '"', '\\');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
