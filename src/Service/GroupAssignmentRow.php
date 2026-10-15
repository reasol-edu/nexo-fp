<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\Teacher;

/**
 * Una fila válida del CSV de asignaciones de docentes a grupos de Séneca, con lo que la importación haría con ella.
 */
final readonly class GroupAssignmentRow
{
    public const NEW             = 'new';              // el docente se añade al grupo
    public const EXISTING        = 'existing';         // ya figura en el grupo
    public const UNKNOWN_GROUP   = 'unknown_group';    // ningún grupo del curso se llama así
    public const UNKNOWN_TEACHER = 'unknown_teacher';  // ningún docente coincide con ese nombre y apellidos

    public function __construct(
        /** Identifica la fila al confirmar la vista previa (grupo y docente, sin distinguir mayúsculas). */
        public string $key,
        public string $groupName,
        /** Nombre tal cual figura en el fichero: «Apellidos, Nombre». */
        public string $teacherName,
        public string $status,
        public ?Group $group,
        public ?Teacher $teacher,
    ) {}

    /** Solo las filas nuevas hacen algo y se pueden marcar. */
    public function isActionable(): bool
    {
        return $this->status === self::NEW;
    }
}
