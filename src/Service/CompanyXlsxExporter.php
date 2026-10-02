<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Company;
use App\Entity\Worker;
use App\Entity\Workcenter;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Libro Excel de empresas pensado para rellenarse a mano y volver a importarse:
 * una hoja por tipo de dato (empresas, centros de trabajo, empleados) más una hoja de instrucciones.
 * Es independiente del XlsxExporter genérico (tabla simple de cabeceras + filas) porque necesita
 * varias hojas, anchos de columna, cabecera fija y celdas siempre de texto.
 */
final class CompanyXlsxExporter
{
    private const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    private const HEADER_COLOR = 'E8EDF7';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * @param list<Company>              $companies
     * @param list<Workcenter>           $workcenters
     * @param array<string, list<Worker>> $workersByCompany Empleados por UUID (RFC 4122) de empresa
     */
    public function createResponse(string $filename, array $companies, array $workcenters, array $workersByCompany): BinaryFileResponse
    {
        $tempPath = sys_get_temp_dir() . '/' . uniqid('nexo_export_', true) . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($tempPath);

        // Empresas
        $this->startSheet($writer, CompanyWorkbook::SHEET_COMPANIES, true);
        foreach ($companies as $company) {
            $writer->addRow($this->textRow($this->companyValues($company)));
        }

        // Centros de trabajo
        $this->startSheet($writer, CompanyWorkbook::SHEET_WORKCENTERS);
        $byId = [];
        foreach ($companies as $company) {
            $byId[$company->getId()->toRfc4122()] = $company;
        }
        foreach ($workcenters as $workcenter) {
            $company = $byId[$workcenter->getCompany()->getId()->toRfc4122()] ?? null;
            if ($company === null) {
                continue;
            }
            $writer->addRow($this->textRow([$company->getVatNumber(), $workcenter->getName(), $workcenter->getCity()]));
        }

        // Empleados
        $this->startSheet($writer, CompanyWorkbook::SHEET_WORKERS);
        foreach ($companies as $company) {
            foreach ($workersByCompany[$company->getId()->toRfc4122()] ?? [] as $worker) {
                $writer->addRow($this->textRow([
                    $company->getVatNumber(),
                    $worker->getName()->getFirstName(),
                    $worker->getName()->getLastName(),
                    $worker->getNationalIdNumber(),
                    $worker->getWorkEmail(),
                    $worker->getWorkPhoneNumber(),
                ]));
            }
        }

        // Instrucciones
        $this->writeHelpSheet($writer);

        $writer->close();

        $response = new BinaryFileResponse($tempPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Type', self::CONTENT_TYPE);
        $response->deleteFileAfterSend(true);

        return $response;
    }

    /** @return list<string|null> */
    private function companyValues(Company $company): array
    {
        $liaisonNames = [];
        foreach ($company->getLiaisons() as $liaison) {
            $liaisonNames[] = $liaison->getName()->getLastName() . ', ' . $liaison->getName()->getFirstName();
        }
        sort($liaisonNames);

        $contact = $company->getContactInformation();

        return [
            $company->getName(),
            $company->getVatNumber(),
            $company->getCity(),
            $company->getRepresentativeFirstName(),
            $company->getRepresentativeLastName(),
            $company->getRepresentativeNationalId(),
            $company->getRepresentativeRole(),
            $contact !== null ? CompanyWorkbook::htmlToPlainText($contact) : null,
            $company->getExceptionalCircumstances(),
            implode('; ', $liaisonNames),
        ];
    }

    /**
     * Abre una hoja con su cabecera. La primera hoja es la que el libro ya tiene abierta.
     */
    private function startSheet(Writer $writer, string $sheetKey, bool $first = false): void
    {
        $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName($this->translator->trans(CompanyWorkbook::sheetKey($sheetKey), [], 'companies'));
        $sheet->setSheetView(new SheetView(freezeRow: 2));

        $headers = [];
        $column  = 1;
        foreach (CompanyWorkbook::COLUMNS[$sheetKey] as $name => [$required, $width]) {
            $label     = $this->translator->trans(CompanyWorkbook::columnKey($sheetKey, $name), [], 'companies');
            $headers[] = $required ? $label . ' *' : $label;
            $sheet->setColumnWidth($width, $column++);
        }

        $style = (new Style())->withFontBold(true)->withBackgroundColor(self::HEADER_COLOR);
        $writer->addRow($this->textRow($headers, $style));
    }

    private function writeHelpSheet(Writer $writer): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $this->configureHelpSheet($sheet);

        $bold = (new Style())->withFontBold(true);
        $writer->addRow($this->textRow([$this->translator->trans('companies.xlsx.help.title', [], 'companies')], $bold));

        foreach (range(1, 12) as $i) {
            $key = 'companies.xlsx.help.' . $i;
            $writer->addRow($this->textRow([$this->translator->trans($key, [], 'companies')]));
        }
    }

    private function configureHelpSheet(Sheet $sheet): void
    {
        $sheet->setName($this->translator->trans(CompanyWorkbook::sheetKey(CompanyWorkbook::SHEET_HELP), [], 'companies'));
        $sheet->setColumnWidth(120, 1);
    }

    /**
     * Fila con todas las celdas como texto. Se evita Row::fromValues() porque interpretaría como
     * fórmula cualquier texto que empiece por «=» y convertiría en número CIF o teléfonos.
     *
     * @param list<string|null> $values
     */
    private function textRow(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(
            static fn (?string $value): StringCell => new StringCell($value ?? '', $style),
            $values,
        ));
    }
}
