<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
    // Separador ';' y BOM UTF-8 para que Excel en es-ES abra el archivo correctamente
    private const SEPARATOR = ';';
    private const BOM       = "\xEF\xBB\xBF";

    /**
     * @param list<string>                                          $headers
     * @param iterable<array<int|string, string|int|float|null>>    $rows
     */
    public function streamResponse(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($headers, $rows): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fwrite($output, self::BOM);
            fputcsv($output, $headers, self::SEPARATOR, '"', '');

            foreach ($rows as $row) {
                fputcsv(
                    $output,
                    array_map(static fn (string|int|float|null $value): string => (string) $value, $row),
                    self::SEPARATOR,
                    '"',
                    ''
                );
            }

            fclose($output);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename)
        );

        return $response;
    }
}
