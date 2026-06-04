<?php

declare(strict_types=1);

namespace App\Service;

use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class PdfService
{
    public function __construct(private readonly Environment $twig) {}

    /**
     * @param array<string, mixed> $context
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

        $currentOptions = array_merge($initialOptions, $options);

        $mpdf = new Mpdf($currentOptions);

        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML($html);

        $content = $mpdf->Output('', 'S');

        return new Response($content, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
