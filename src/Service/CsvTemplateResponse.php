<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;

/**
 * Respuesta de descarga de una plantilla CSV (cabeceras y unas filas de ejemplo) para las importaciones.
 *
 * El fichero va en UTF-8 con BOM y separado por comas, el formato que leen las importaciones: así
 * Excel abre bien las tildes y la importación reconoce las columnas tal cual.
 */
final class CsvTemplateResponse
{
    /**
     * @param list<string>       $headers
     * @param list<list<string>> $exampleRows
     */
    public function create(string $filename, array $headers, array $exampleRows): Response
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ([$headers, ...$exampleRows] as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
