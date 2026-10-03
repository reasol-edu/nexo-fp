<?php

declare(strict_types=1);

namespace App\Service;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class PdfService
{
    /** Clave de fuente en mPDF: minúsculas y sin espacios, como las que trae el propio mPDF (dejavusans…). */
    private const FONT_NAME = 'sourcesanspro';

    public function __construct(
        private readonly Environment $twig,
        #[Autowire('%kernel.project_dir%/config/pdf/fonts')]
        private readonly string $fontDir,
    ) {}

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     * @throws MpdfException
     */
    public function renderPdf(string $template, array $context, string $filename, array $options = []): Response
    {
        $html = $this->twig->render($template, $context);

        $initialOptions = [
            'mode'          => 'utf-8',
            'format'        => 'A4-L',
            'margin_top'    => 40,
            'margin_right'  => 12,
            'margin_bottom' => 20,
            'margin_left'   => 12,
            'margin_header' => 4,
            'margin_footer' => 4,
        ];

        $currentOptions = array_merge($initialOptions, $this->fontConfig(), $options);

        $mpdf = new Mpdf($currentOptions);

        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML($html);

        $content = $mpdf->Output('', 'S');

        return new Response($content, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Registra Source Sans Pro (config/pdf/fonts/) como fuente propia de mPDF y la fija como la
     * predeterminada del documento, de modo que todos los PDF la usan sin que cada plantilla tenga
     * que pedirla (templates/pdf/_styles.html.twig también la nombra de forma explícita).
     *
     * Sustituye a DejaVu Sans, la fuente por defecto de mPDF: es más legible a tamaños pequeños y
     * mucho más estrecha, lo que permite más texto por línea sin reducir el cuerpo de letra.
     *
     * @return array<string, mixed>
     */
    private function fontConfig(): array
    {
        $configDefaults = (new ConfigVariables())->getDefaults();
        $fontDefaults   = (new FontVariables())->getDefaults();

        $fontDirs = \is_array($configDefaults) && \is_array($configDefaults['fontDir'] ?? null) ? $configDefaults['fontDir'] : [];
        $fontData = \is_array($fontDefaults) && \is_array($fontDefaults['fontdata'] ?? null) ? $fontDefaults['fontdata'] : [];

        return [
            'fontDir'      => array_merge($fontDirs, [$this->fontDir]),
            'fontdata'     => $fontData + [
                self::FONT_NAME => [
                    'R'  => 'SourceSansPro-Regular.ttf',
                    'B'  => 'SourceSansPro-Bold.ttf',
                    'I'  => 'SourceSansPro-It.ttf',
                    'BI' => 'SourceSansPro-BoldIt.ttf',
                ],
            ],
            'default_font' => self::FONT_NAME,
        ];
    }
}
