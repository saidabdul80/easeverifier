<?php

namespace App\Support;

use Closure;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    /**
     * @param  array<int, string>  $headers
     * @param  Closure(): iterable<int, array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headers, Closure $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // UTF-8 BOM improves Excel compatibility for exported CSV files.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows() as $row) {
                fputcsv($handle, array_map(self::normalizeValue(...), $row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private static function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
