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
