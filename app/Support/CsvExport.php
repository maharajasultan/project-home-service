<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    /**
     * Unduh CSV (pemisah ";" dan BOM UTF-8 agar rapi di Excel Indonesia).
     *
     * @param  iterable<array<int,mixed>>  $rows  boleh generator agar hemat memori
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_map([self::class, 'cell'], $header), ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row), ';', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cegah CSV injection: teks yang diawali = + - @ dianggap rumus oleh Excel. */
    public static function cell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}