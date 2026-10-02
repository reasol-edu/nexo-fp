<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Worker;
use App\Entity\Workcenter;
use App\Repository\CompanyRepository;
use App\Repository\WorkcenterRepository;
use App\Repository\WorkerRepository;
use App\Security\Voter\CompanyVoter;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\Sheet;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Importa el libro Excel de empresas generado por CompanyXlsxExporter (o rellenado a mano con la
 * misma estructura). Reglas:
 *
 *  - Las empresas se identifican por su CIF/NIF dentro del centro seleccionado.
 *  - Solo se añade o se actualiza; nunca se elimina nada que no aparezca en el fichero.
 *  - Al actualizar, una celda vacía conserva el valor actual.
 *  - Los empleados se identifican por su DNI/NIE (único en toda la aplicación): si ya existe, se
 *    vincula a la empresa sin modificar sus datos.
 *  - Todo o nada: si alguna fila es errónea no se escribe nada.
 *
 * @phpstan-type CompanyFields array{name: string, vat_number: string, city: string, representative_first_name: ?string, representative_last_name: ?string, representative_national_id: ?string, representative_role: ?string, contact_information: ?string, exceptional_circumstances: ?string}
 */
final class CompanyXlsxImporter
{
    private const MAX_ERRORS = 100;

    /** Propiedad de la entidad => columna de la hoja de empresas, para rotular los errores de validación. */
    private const PROPERTY_COLUMNS = [
        'name'                    => 'name',
        'vatNumber'               => 'vat_number',
        'city'                    => 'city',
        'representativeFirstName' => 'representative_first_name',
        'representativeLastName'  => 'representative_last_name',
        'representativeNationalId' => 'representative_national_id',
        'representativeRole'      => 'representative_role',
    ];

    /** @var list<\Closure(): void> */
    private array $operations = [];
    private CompanyImportResult $result;

    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly WorkcenterRepository $workcenters,
        private readonly WorkerRepository $workers,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
        private readonly AuthorizationCheckerInterface $authorization,
        #[Autowire(service: 'html_sanitizer.sanitizer.app.company_contact')]
        private readonly HtmlSanitizerInterface $contactSanitizer,
    ) {}

    /**
     * @param bool $apply false = simulación (no escribe nada)
     */
    public function import(string $path, EducationalCentre $centre, bool $apply): CompanyImportResult
    {
        $this->result     = new CompanyImportResult();
        $this->operations = [];

        $sheets = $this->readWorkbook($path);
        if (!$this->result->hasErrors()) {
            $this->process($sheets, $centre);
        }

        if ($apply && !$this->result->hasErrors() && $this->operations !== []) {
            $operations = $this->operations;
            $this->em->wrapInTransaction(static function () use ($operations): void {
                foreach ($operations as $operation) {
                    $operation();
                }
            });
        }

        return $this->result;
    }

    // ── Lectura del fichero ───────────────────────────────────────────────────

    /**
     * @return array<string, list<array{row: int, values: array<string, string>}>> filas por hoja (clave de hoja)
     */
    private function readWorkbook(string $path): array
    {
        $sheetNames = [];
        foreach ([CompanyWorkbook::SHEET_COMPANIES, CompanyWorkbook::SHEET_WORKCENTERS, CompanyWorkbook::SHEET_WORKERS] as $key) {
            $sheetNames[CompanyWorkbook::normalize($this->tr(CompanyWorkbook::sheetKey($key)))] = $key;
        }

        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        try {
            $reader->open($path);
        } catch (\Throwable) {
            $this->fail($this->tr('companies.import.error.invalid_file'));

            return [];
        }

        $data = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $key = $sheetNames[CompanyWorkbook::normalize($sheet->getName())] ?? null;
                if ($key === null || isset($data[$key])) {
                    continue;
                }
                $data[$key] = $this->readSheet($key, $sheet);
            }
        } catch (\Throwable) {
            $this->fail($this->tr('companies.import.error.invalid_file'));

            return [];
        } finally {
            $reader->close();
        }

        if (!isset($data[CompanyWorkbook::SHEET_COMPANIES])) {
            $this->fail($this->tr('companies.import.error.no_companies_sheet'));
        }

        return $data;
    }

    /**
     * @return list<array{row: int, values: array<string, string>}>
     */
    private function readSheet(string $sheetKey, Sheet $sheet): array
    {
        $labels = [];
        foreach (array_keys(CompanyWorkbook::COLUMNS[$sheetKey]) as $column) {
            if (!\in_array($column, CompanyWorkbook::INFORMATIVE_COLUMNS, true)) {
                $labels[CompanyWorkbook::normalize($this->tr(CompanyWorkbook::columnKey($sheetKey, $column)))] = $column;
            }
        }

        $columnByIndex = null;
        $rows          = [];
        $rowNumber     = 0;

        foreach ($sheet->getRowIterator() as $row) {
            ++$rowNumber;
            $cells = array_map($this->cellToString(...), $row->toArray());
            if (implode('', $cells) === '') {
                continue;
            }

            if ($columnByIndex === null) {
                $columnByIndex = [];
                foreach ($cells as $index => $label) {
                    $column = $labels[CompanyWorkbook::normalize($label)] ?? null;
                    if ($column !== null && !\in_array($column, $columnByIndex, true)) {
                        $columnByIndex[$index] = $column;
                    }
                }
                foreach (CompanyWorkbook::COLUMNS[$sheetKey] as $column => [$required]) {
                    if ($required && !\in_array($column, $columnByIndex, true)) {
                        $this->fail($this->tr('companies.import.error.missing_column', [
                            '%sheet%'  => $this->tr(CompanyWorkbook::sheetKey($sheetKey)),
                            '%column%' => $this->tr(CompanyWorkbook::columnKey($sheetKey, $column)),
                        ]));
                    }
                }

                continue;
            }

            $values = array_fill_keys($columnByIndex, '');
            foreach ($columnByIndex as $index => $column) {
                $values[$column] = $cells[$index] ?? '';
            }
            $rows[] = ['row' => $rowNumber, 'values' => $values];
        }

        return $rows;
    }

    private function cellToString(mixed $value): string
    {
        return match (true) {
            $value === null                       => '',
            $value instanceof \DateTimeInterface  => $value->format('Y-m-d'),
            \is_bool($value)                      => $value ? '1' : '0',
            \is_int($value)                       => (string) $value,
            \is_float($value)                     => $value === floor($value) && abs($value) < 1e15
                ? (string) (int) $value
                : rtrim(rtrim(sprintf('%.15F', $value), '0'), '.'),
            \is_string($value)                    => trim($value),
            $value instanceof \Stringable         => trim((string) $value),
            default                               => '',
        };
    }

    // ── Proceso ───────────────────────────────────────────────────────────────

    /**
     * @param array<string, list<array{row: int, values: array<string, string>}>> $sheets
     */
    private function process(array $sheets, EducationalCentre $centre): void
    {
        /** @var array<string, Company> $existing empresas del centro por clave de CIF */
        $existing = [];
        foreach ($this->companies->findAllByCentre($centre) as $company) {
            $existing[CompanyWorkbook::vatKey($company->getVatNumber())] = $company;
        }

        /** @var array<string, list<Workcenter>> $centresByCompany */
        $centresByCompany = [];
        foreach ($this->workcenters->findByCentreOrdered($centre) as $workcenter) {
            $centresByCompany[$workcenter->getCompany()->getId()->toRfc4122()][] = $workcenter;
        }

        /** @var array<string, array{company: ?Company, city: string, entity: Company, editable: bool}> $targets */
        $targets = [];
        foreach ($existing as $key => $company) {
            $targets[$key] = [
                'company'  => $company,
                'city'     => $company->getCity(),
                'entity'   => $company,
                'editable' => $this->authorization->isGranted(CompanyVoter::EDIT, $company),
            ];
        }

        $companiesWithWorkcenterRows = [];

        $this->processCompanies($sheets[CompanyWorkbook::SHEET_COMPANIES] ?? [], $centre, $existing, $targets);
        $this->processWorkcenters($sheets[CompanyWorkbook::SHEET_WORKCENTERS] ?? [], $targets, $centresByCompany, $companiesWithWorkcenterRows);
        $this->addDefaultWorkcenters($targets, $existing, $companiesWithWorkcenterRows);
        $this->processWorkers($sheets[CompanyWorkbook::SHEET_WORKERS] ?? [], $targets);
    }

    /**
     * @param list<array{row: int, values: array<string, string>}>                                       $rows
     * @param array<string, Company>                                                                      $existing
     * @param array<string, array{company: ?Company, city: string, entity: Company, editable: bool}>      $targets
     */
    private function processCompanies(array $rows, EducationalCentre $centre, array $existing, array &$targets): void
    {
        $sheet = CompanyWorkbook::SHEET_COMPANIES;
        $seen  = [];

        foreach ($rows as ['row' => $row, 'values' => $v]) {
            $key = CompanyWorkbook::vatKey($v['vat_number']);

            $missing = false;
            foreach (['name', 'vat_number', 'city'] as $required) {
                if ($v[$required] === '') {
                    $this->rowError($sheet, $row, $this->tr('companies.import.error.required', ['%column%' => $this->tr(CompanyWorkbook::columnKey($sheet, $required))]));
                    $missing = true;
                }
            }
            if ($missing) {
                continue;
            }

            if (isset($seen[$key])) {
                $this->rowError($sheet, $row, $this->tr('companies.import.error.duplicate_vat', ['%row%' => $seen[$key]]));

                continue;
            }
            $seen[$key] = $row;

            $current = $existing[$key] ?? null;
            if ($current !== null && !$targets[$key]['editable']) {
                $this->rowError($sheet, $row, $this->tr('companies.import.error.not_allowed'));

                continue;
            }

            // Valores resultantes: los del fichero y, si la celda está vacía, los actuales.
            $fields = $this->companyFields($v, $current);

            if (!$this->validateCompany($fields, $centre, $row)) {
                continue;
            }

            if ($current === null) {
                $company = new Company();
                $this->fillCompany($company, $fields);
                $company->setEducationalCentre($centre);
                $targets[$key] = ['company' => null, 'city' => $company->getCity(), 'entity' => $company, 'editable' => true];
                $this->operations[] = function () use ($company): void {
                    $this->em->persist($company);
                };
                ++$this->result->companiesCreated;

                continue;
            }

            if ($this->companyChanged($current, $fields)) {
                $this->operations[] = function () use ($current, $fields): void {
                    $this->fillCompany($current, $fields);
                };
                ++$this->result->companiesUpdated;
            } else {
                ++$this->result->companiesUnchanged;
            }
            $targets[$key] = ['company' => $current, 'city' => $fields['city'], 'entity' => $current, 'editable' => true];
        }
    }

    /**
     * @param array<string, string> $v
     * @return CompanyFields
     */
    private function companyFields(array $v, ?Company $current): array
    {
        $keep = static fn (string $new, ?string $old): ?string => $new !== '' ? $new : $old;

        $contact = $current?->getContactInformation();
        $contactHtml = $contact;
        if ($v['contact_information'] !== ''
            && ($contact === null || CompanyWorkbook::htmlToPlainText($contact) !== CompanyWorkbook::htmlToPlainText(CompanyWorkbook::plainTextToHtml($v['contact_information'])))
        ) {
            $contactHtml = $this->contactSanitizer->sanitize(CompanyWorkbook::plainTextToHtml($v['contact_information']));
        }

        return [
            'name'                       => $v['name'],
            'vat_number'                 => $v['vat_number'],
            'city'                       => $v['city'],
            'representative_first_name'  => $keep($v['representative_first_name'], $current?->getRepresentativeFirstName()),
            'representative_last_name'   => $keep($v['representative_last_name'], $current?->getRepresentativeLastName()),
            'representative_national_id' => $keep($v['representative_national_id'], $current?->getRepresentativeNationalId()),
            'representative_role'        => $keep($v['representative_role'], $current?->getRepresentativeRole()),
            'contact_information'        => $contactHtml !== '' ? $contactHtml : null,
            'exceptional_circumstances'  => $keep($v['exceptional_circumstances'], $current?->getExceptionalCircumstances()),
        ];
    }

    /** @param CompanyFields $f */
    private function fillCompany(Company $company, array $f): void
    {
        $company->setName((string) $f['name'])
            ->setVatNumber((string) $f['vat_number'])
            ->setCity((string) $f['city'])
            ->setRepresentativeFirstName($f['representative_first_name'])
            ->setRepresentativeLastName($f['representative_last_name'])
            ->setRepresentativeNationalId($f['representative_national_id'])
            ->setRepresentativeRole($f['representative_role'])
            ->setContactInformation($f['contact_information'])
            ->setExceptionalCircumstances($f['exceptional_circumstances']);
    }

    /** @param CompanyFields $f */
    private function companyChanged(Company $company, array $f): bool
    {
        return $company->getName() !== $f['name']
            || $company->getVatNumber() !== $f['vat_number']
            || $company->getCity() !== $f['city']
            || $company->getRepresentativeFirstName() !== $f['representative_first_name']
            || $company->getRepresentativeLastName() !== $f['representative_last_name']
            || $company->getRepresentativeNationalId() !== $f['representative_national_id']
            || $company->getRepresentativeRole() !== $f['representative_role']
            || $company->getContactInformation() !== $f['contact_information']
            || $company->getExceptionalCircumstances() !== $f['exceptional_circumstances'];
    }

    /** @param CompanyFields $fields */
    private function validateCompany(array $fields, EducationalCentre $centre, int $row): bool
    {
        $probe = new Company();
        $this->fillCompany($probe, $fields);
        $probe->setEducationalCentre($centre);

        $valid = true;
        foreach ($this->validator->validate($probe) as $violation) {
            $column = self::PROPERTY_COLUMNS[$violation->getPropertyPath()] ?? null;
            $label  = $column !== null ? $this->tr(CompanyWorkbook::columnKey(CompanyWorkbook::SHEET_COMPANIES, $column)) : $violation->getPropertyPath();
            $this->rowError(CompanyWorkbook::SHEET_COMPANIES, $row, $label . ': ' . $violation->getMessage());
            $valid = false;
        }

        return $valid;
    }

    /**
     * @param list<array{row: int, values: array<string, string>}>                                       $rows
     * @param array<string, array{company: ?Company, city: string, entity: Company, editable: bool}>      $targets
     * @param array<string, list<Workcenter>>                                                             $centresByCompany
     * @param array<string, true>                                                                         $withRows
     */
    private function processWorkcenters(array $rows, array $targets, array $centresByCompany, array &$withRows): void
    {
        $sheet = CompanyWorkbook::SHEET_WORKCENTERS;
        $seen  = [];

        foreach ($rows as ['row' => $row, 'values' => $v]) {
            $key = CompanyWorkbook::vatKey($v['company_vat_number']);

            if ($v['company_vat_number'] === '' || $v['name'] === '') {
                $required = $v['company_vat_number'] === '' ? 'company_vat_number' : 'name';
                $this->rowError($sheet, $row, $this->tr('companies.import.error.required', ['%column%' => $this->tr(CompanyWorkbook::columnKey($sheet, $required))]));

                continue;
            }

            $target = $targets[$key] ?? null;
            if ($target === null) {
                $this->rowError($sheet, $row, $this->tr('companies.import.error.unknown_company', ['%vat%' => $v['company_vat_number']]));

                continue;
            }
            if (!$target['editable']) {
                $this->rowError($sheet, $row, $this->tr('companies.import.error.not_allowed'));

                continue;
            }

            $name = $v['name'];
            $city = $v['city'] !== '' ? $v['city'] : null;
            if (mb_strlen($name) > 255 || ($city !== null && mb_strlen($city) > 255)) {
                $this->rowError($sheet, $row, $this->tr('companies.import.error.too_long'));

                continue;
            }

            $dupKey = $key . '|' . mb_strtolower($name);
            if (isset($seen[$dupKey])) {
                $this->rowError($sheet, $row, $this->tr('companies.import.error.duplicate_workcenter', ['%row%' => $seen[$dupKey]]));

                continue;
            }
            $seen[$dupKey]  = $row;
            $withRows[$key] = true;

            $company = $target['entity'];
            $match   = null;
            if ($target['company'] !== null) {
                foreach ($centresByCompany[$company->getId()->toRfc4122()] ?? [] as $workcenter) {
                    if (mb_strtolower($workcenter->getName()) === mb_strtolower($name)) {
                        $match = $workcenter;
                        break;
                    }
                }
            }

            if ($match === null) {
                $workcenter = (new Workcenter())->setName($name)->setCity($city ?? $target['city'])->setCompany($company);
                $this->operations[] = function () use ($workcenter): void {
                    $this->em->persist($workcenter);
                };
                ++$this->result->workcentersCreated;
            } elseif ($city !== null && $city !== $match->getCity()) {
                $this->operations[] = static function () use ($match, $city): void {
                    $match->setCity($city);
                };
                ++$this->result->workcentersUpdated;
            }
        }
    }

    /**
     * Igual que al crear una empresa desde el formulario: una sede por defecto si el libro no lista ninguna.
     *
     * @param array<string, array{company: ?Company, city: string, entity: Company, editable: bool}> $targets
     * @param array<string, Company>                                                                  $existing
     * @param array<string, true>                                                                     $withRows
     */
    private function addDefaultWorkcenters(array $targets, array $existing, array $withRows): void
    {
        foreach ($targets as $key => $target) {
            if (isset($existing[$key]) || isset($withRows[$key])) {
                continue;
            }
            $workcenter = (new Workcenter())
                ->setName($this->translator->trans('workcenter.default_name', [], 'companies'))
                ->setCity($target['city'])
                ->setCompany($target['entity']);
            $this->operations[] = function () use ($workcenter): void {
                $this->em->persist($workcenter);
            };
            ++$this->result->workcentersCreated;
        }
    }

    /**
     * @param list<array{row: int, values: array<string, string>}>                                       $rows
     * @param array<string, array{company: ?Company, city: string, entity: Company, editable: bool}>      $targets
     */
    private function processWorkers(array $rows, array $targets): void
    {
        $sheet = CompanyWorkbook::SHEET_WORKERS;
        $seen  = [];
        /** @var array<string, Worker> $created trabajadores nuevos de esta importación, por DNI/NIE */
        $created = [];

        foreach ($rows as ['row' => $row, 'values' => $v]) {
            $key = CompanyWorkbook::vatKey($v['company_vat_number']);

            foreach (['company_vat_number', 'first_name', 'last_name', 'national_id'] as $required) {
                if ($v[$required] === '') {
                    $this->rowError($sheet, $row, $this->tr('companies.import.error.required', ['%column%' => $this->tr(CompanyWorkbook::columnKey($sheet, $required))]));

                    continue 2;
                }
            }

            $target = $targets[$key] ?? null;
            if ($target === null) {
                $this->rowError($sheet, $row, $this->tr('companies.import.error.unknown_company', ['%vat%' => $v['company_vat_number']]));

                continue;
            }
            if (!$target['editable']) {
                $this->rowError($sheet, $row, $this->tr('companies.import.error.not_allowed'));

                continue;
            }

            $nationalId = $v['national_id'];
            $dupKey     = $key . '|' . mb_strtoupper($nationalId);
            if (isset($seen[$dupKey])) {
                $this->rowError($sheet, $row, $this->tr('companies.import.error.duplicate_worker', ['%row%' => $seen[$dupKey]]));

                continue;
            }
            $seen[$dupKey] = $row;

            $company = $target['entity'];
            $worker  = $created[$nationalId] ?? $this->workers->findByNationalIdNumber($nationalId);

            if ($worker !== null) {
                if ($target['company'] !== null && $company->getWorkers()->contains($worker)) {
                    continue; // ya vinculado: sin cambios
                }
                $this->operations[] = static function () use ($company, $worker): void {
                    $company->addWorker($worker);
                };
                ++$this->result->workersLinked;

                continue;
            }

            $worker = new Worker(new PersonName($v['first_name'], $v['last_name']));
            $worker->setNationalIdNumber($nationalId);
            $worker->setWorkEmail($v['work_email'] !== '' ? $v['work_email'] : null);
            $worker->setWorkPhoneNumber($v['work_phone'] !== '' ? $v['work_phone'] : null);

            $violations = $this->validator->validate($worker);
            $violations->addAll($this->validator->validate($worker->getName()));
            if ($violations->count() > 0) {
                foreach ($violations as $violation) {
                    $this->rowError($sheet, $row, $violation->getMessage());
                }

                continue;
            }

            $created[$nationalId] = $worker;
            $this->operations[] = function () use ($company, $worker): void {
                $this->em->persist($worker);
                $company->addWorker($worker);
            };
            ++$this->result->workersCreated;
        }
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    private function rowError(string $sheetKey, int $row, string $message): void
    {
        $this->fail($this->tr('companies.import.error.row', [
            '%sheet%'   => $this->tr(CompanyWorkbook::sheetKey($sheetKey)),
            '%row%'     => $row,
            '%message%' => $message,
        ]));
    }

    private function fail(string $message): void
    {
        if (\count($this->result->errors) < self::MAX_ERRORS) {
            $this->result->errors[] = $message;
        }
    }

    /** @param array<string, string|int> $params */
    private function tr(string $key, array $params = []): string
    {
        return $this->translator->trans($key, $params, 'companies');
    }
}
