<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Resumen de una importación de empresas (o de su simulación previa).
 * Si hay errores, no se ha escrito nada.
 */
final class CompanyImportResult
{
    public int $companiesCreated = 0;
    public int $companiesUpdated = 0;
    public int $companiesUnchanged = 0;
    public int $workcentersCreated = 0;
    public int $workcentersUpdated = 0;
    public int $workersCreated = 0;
    public int $workersLinked = 0;

    /** @var list<string> */
    public array $errors = [];

    /**
     * Empresas de la hoja «Empresas», para que la vista previa permita elegir cuáles se importan.
     * `status`: new (se crea), update (se actualiza) o same (la empresa no cambia; pueden cambiar sus centros o empleados).
     *
     * @var array<string, array{key: string, vat: string, name: string, city: string, status: string}>
     */
    public array $companies = [];

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /** Hay algo que escribir. */
    public function hasChanges(): bool
    {
        return $this->companiesCreated + $this->companiesUpdated + $this->workcentersCreated
            + $this->workcentersUpdated + $this->workersCreated + $this->workersLinked > 0;
    }
}
