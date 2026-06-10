<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\CsvExporter;
use PHPUnit\Framework\TestCase;

class CsvExporterTest extends TestCase
{
    private const BOM = "\xEF\xBB\xBF";

    public function testResponseHasCsvContentTypeAndAttachmentDisposition(): void
    {
        $response = (new CsvExporter())->streamResponse('datos.csv', ['A'], []);

        self::assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=datos.csv', $response->headers->get('Content-Disposition'));
    }

    public function testOutputStartsWithUtf8Bom(): void
    {
        $csv = $this->capture(['Nombre'], []);

        self::assertStringStartsWith(self::BOM, $csv);
    }

    public function testUsesSemicolonSeparator(): void
    {
        $csv = $this->capture(['Nombre', 'Ciudad'], [['Acme', 'Sevilla']]);

        $lines = explode("\n", trim(substr($csv, strlen(self::BOM))));

        self::assertSame('Nombre;Ciudad', trim($lines[0]));
        self::assertSame('Acme;Sevilla', trim($lines[1]));
    }

    public function testQuotesValuesContainingSeparator(): void
    {
        $csv = $this->capture(['Col'], [['uno; dos']]);

        self::assertStringContainsString('"uno; dos"', $csv);
    }

    public function testCastsNullAndNumericValuesToString(): void
    {
        $csv = $this->capture(['A', 'B', 'C'], [[null, 42, 'x']]);

        $lines = explode("\n", trim(substr($csv, strlen(self::BOM))));

        self::assertSame(';42;x', trim($lines[1]));
    }

    /**
     * @param list<string>                                       $headers
     * @param iterable<array<int|string, string|int|float|null>> $rows
     */
    private function capture(array $headers, iterable $rows): string
    {
        $response = (new CsvExporter())->streamResponse('test.csv', $headers, $rows);

        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
