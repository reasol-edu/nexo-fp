<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Estructura del libro Excel de empresas, compartida por la exportación y la importación:
 * hojas, columnas (en orden), si son obligatorias y su ancho. Los rótulos visibles salen de
 * las traducciones `companies.xlsx.*` del dominio «companies».
 */
final class CompanyWorkbook
{
    public const SHEET_COMPANIES   = 'companies';
    public const SHEET_WORKCENTERS = 'workcenters';
    public const SHEET_WORKERS     = 'workers';
    public const SHEET_HELP        = 'help';

    /**
     * Columna => [obligatoria, ancho]. La columna «liaisons» es solo informativa:
     * se exporta pero se ignora al importar (asignar un docente de enlace concede permisos de edición).
     *
     * @var array<string, array<string, array{0: bool, 1: int}>>
     */
    public const COLUMNS = [
        self::SHEET_COMPANIES => [
            'name'                       => [true, 34],
            'vat_number'                 => [true, 16],
            'city'                       => [true, 22],
            'representative_first_name'  => [false, 22],
            'representative_last_name'   => [false, 26],
            'representative_national_id' => [false, 18],
            'representative_role'        => [false, 24],
            'contact_information'        => [false, 44],
            'exceptional_circumstances'  => [false, 44],
            'liaisons'                   => [false, 32],
        ],
        self::SHEET_WORKCENTERS => [
            'company_vat_number' => [true, 18],
            'name'               => [true, 34],
            'city'               => [false, 22],
        ],
        self::SHEET_WORKERS => [
            'company_vat_number' => [true, 18],
            'first_name'         => [true, 22],
            'last_name'          => [true, 28],
            'national_id'        => [true, 16],
            'work_email'         => [false, 32],
            'work_phone'         => [false, 18],
        ],
    ];

    /** Columnas que el importador no lee. */
    public const INFORMATIVE_COLUMNS = ['liaisons'];

    public static function sheetKey(string $sheet): string
    {
        return 'companies.xlsx.sheet.' . $sheet;
    }

    public static function columnKey(string $sheet, string $column): string
    {
        return 'companies.xlsx.col.' . $sheet . '.' . $column;
    }

    /**
     * Forma canónica para comparar rótulos escritos a mano: sin asterisco final,
     * sin tildes, en minúsculas y con los espacios colapsados.
     */
    public static function normalize(string $label): string
    {
        $label = trim($label);
        $label = rtrim($label, "* \t");
        $ascii = \function_exists('transliterator_transliterate')
            ? transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $label)
            : mb_strtolower($label);

        return trim((string) preg_replace('/\s+/', ' ', \is_string($ascii) ? $ascii : mb_strtolower($label)));
    }

    /** Texto plano de un HTML del editor enriquecido (un salto de línea por párrafo, elemento de lista o <br>). */
    public static function htmlToPlainText(string $html): string
    {
        $text = (string) preg_replace('#</(p|li|div|h[1-6])>|<br\s*/?>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim((string) preg_replace("/\n{2,}/", "\n", $text));
    }

    /** HTML básico (un párrafo por línea) a partir de un texto plano escrito en una celda. */
    public static function plainTextToHtml(string $text): string
    {
        $lines = preg_split("/\r\n|\r|\n/", trim($text)) ?: [];
        $html  = '';
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $html .= '<p>' . htmlspecialchars(trim($line), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>';
            }
        }

        return $html;
    }

    /** Clave de comparación de un CIF/NIF: sin espacios ni guiones y en mayúsculas. */
    public static function vatKey(string $vat): string
    {
        return mb_strtoupper((string) preg_replace('/[\s\-.]+/', '', $vat));
    }
}
