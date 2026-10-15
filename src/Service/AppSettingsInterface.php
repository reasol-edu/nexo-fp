<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EducationalCentre;
use App\Entity\Teacher;

interface AppSettingsInterface
{
    /** Returns the resolved value for the current user / selected centre context. */
    public function get(string $key): mixed;

    /**
     * Returns the global value (global → default), ignoring centre and teacher. It needs no session
     * or selected centre, so it is the one to use from the Messenger worker and the scheduler.
     */
    public function getGlobal(string $key): mixed;

    /** Returns the resolved value for a specific teacher (teacher → global → default, no centre). */
    public function getForTeacher(string $key, Teacher $teacher): mixed;

    /** Returns the resolved value for a specific centre (centre → global → default, no teacher). */
    public function getForCentre(string $key, EducationalCentre $centre): mixed;

    /**
     * Resuelve varios ajustes de un centro con una sola consulta (centro → global → predeterminado).
     *
     * @param list<string> $keys
     * @return array<string, mixed> valor de cada clave (null si no existe el ajuste)
     */
    public function getManyForCentre(array $keys, EducationalCentre $centre): array;
}
