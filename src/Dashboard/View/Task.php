<?php

declare(strict_types=1);

namespace App\Dashboard\View;

use App\Dashboard\Severity;

/** Un problema accionable de «Atención ahora»: una frase con número, un plazo y un enlace al filtro. */
final readonly class Task
{
    public const STUDENTS_WITHOUT_POSITION = 'students_without_position';
    public const MISSING_TUTOR             = 'missing_tutor';
    public const MISSING_MENTOR            = 'missing_mentor';
    public const READY_FOR_SENECA          = 'ready_for_seneca';
    public const REGISTERED_UNSIGNED       = 'registered_unsigned';
    public const SUPPLY_DEFICIT            = 'supply_deficit';
    public const COMPANY_WITHOUT_WORKERS   = 'company_without_workers';
    public const MY_UNSIGNED               = 'my_unsigned';
    public const GROUP_WITHOUT_POSITION    = 'group_without_position';

    public function __construct(
        public string $key,
        public Severity $severity,
        public int $count,
        public string $stayId,
        public string $stayName,
        /** Días hasta el inicio de la estancia; negativo si ya empezó. */
        public int $daysToStart,
        /** Filtro rápido del detalle de la estancia que lleva a las filas afectadas. */
        public ?string $filter = null,
        /** Empresa o grupo al que se refiere la tarea, si lo hay. */
        public ?string $subject = null,
        /** Segunda cifra de la frase (p. ej. los estudiantes que esperan puesto en un déficit). */
        public int $extra = 0,
        /** Identificador de la empresa a la que se refiere la tarea, para enlazar con su ficha. */
        public ?string $subjectId = null,
    ) {}
}
