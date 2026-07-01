<?php

declare(strict_types=1);

namespace App\Support;

final class Response
{
    public static function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }

    public static function csv(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, $headers);

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
