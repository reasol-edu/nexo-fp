<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Repository\ProgrammeYearRepository;

/**
 * Reparto de responsabilidades dentro de una estancia con varios niveles.
 *
 * Un docente gestiona a los estudiantes de los niveles de la estancia que coordina o cuya familia
 * profesional dirige; la dirección del centro y la administración global los gestionan todos. Quien
 * coordina solo 2.º de una enseñanza no gestiona a los de 1.º, aunque su enseñanza participe en la estancia.
 * Quien no gestiona a un estudiante puede verlo (y ver su puesto), pero no modificarlo.
 *
 * Se cachea por docente y estancia durante la petición: la pantalla de una estancia consulta
 * el alcance de cada estudiante y no debe lanzar una consulta por cada uno.
 */
final class StayScope
{
    /** @var array<string, list<string>|null> ids de niveles gestionables (null = todos), por «docente|estancia» */
    private array $cache = [];

    public function __construct(private readonly ProgrammeYearRepository $levels) {}

    /**
     * Ids (RFC 4122) de los niveles de la estancia cuyos estudiantes gestiona el docente,
     * o null si los gestiona todos (administración global o dirección del centro).
     *
     * @return list<string>|null
     */
    public function manageableProgrammeYearIds(Teacher $user, Stay $stay): ?array
    {
        $key = $user->getId()->toRfc4122() . '|' . $stay->getId()->toRfc4122();
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if ($user->isAdmin() || $stay->getAcademicYear()->getEducationalCentre()->getAdmins()->contains($user)) {
            return $this->cache[$key] = null;
        }

        $ids = [];
        foreach ($this->levels->findCoordinatedByInStay($user, $stay) as $level) {
            $ids[$level->getId()->toRfc4122()] = true;
        }
        foreach ($this->levels->findHeadedByInStay($user, $stay) as $level) {
            $ids[$level->getId()->toRfc4122()] = true;
        }

        return $this->cache[$key] = array_keys($ids);
    }

    /** ¿Gestiona el docente algún nivel de la estancia? (si no, la estancia no es suya) */
    public function canManageAny(Teacher $user, Stay $stay): bool
    {
        $ids = $this->manageableProgrammeYearIds($user, $stay);

        return $ids === null || $ids !== [];
    }

    public function canManageProgrammeYear(Teacher $user, Stay $stay, ProgrammeYear $level): bool
    {
        $ids = $this->manageableProgrammeYearIds($user, $stay);

        return $ids === null || in_array($level->getId()->toRfc4122(), $ids, true);
    }

    /** ¿Gestiona el docente todos los niveles de la estancia? (p. ej., para poder eliminarla) */
    public function canManageEveryLevel(Teacher $user, Stay $stay): bool
    {
        $ids = $this->manageableProgrammeYearIds($user, $stay);
        if ($ids === null) {
            return true;
        }

        foreach ($stay->getProgrammeYears() as $level) {
            if (!in_array($level->getId()->toRfc4122(), $ids, true)) {
                return false;
            }
        }

        return $stay->getProgrammeYears()->count() > 0;
    }

    /** ¿Gestiona el docente algún nivel de esa enseñanza que forme parte de la estancia? */
    public function canManageProgramme(Teacher $user, Stay $stay, Programme $programme): bool
    {
        $ids = $this->manageableProgrammeYearIds($user, $stay);
        if ($ids === null) {
            return true;
        }

        foreach ($stay->getProgrammeYears() as $level) {
            if ($level->getProgramme()->getId()->equals($programme->getId()) && in_array($level->getId()->toRfc4122(), $ids, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ¿Gestiona el docente a este estudiante? Sí si gestiona alguno de los niveles de la estancia a los que
     * pertenece por sus grupos. Un estudiante sin grupo en ningún nivel de la estancia lo gestiona cualquiera
     * que gestione algún nivel.
     */
    public function canManageStudent(Teacher $user, Stay $stay, Student $student): bool
    {
        $ids = $this->manageableProgrammeYearIds($user, $stay);
        if ($ids === null) {
            return true;
        }
        if ($ids === []) {
            return false;
        }

        $studentLevels = $stay->getProgrammeYearsOfStudent($student);
        if ($studentLevels === []) {
            return true;
        }

        foreach ($studentLevels as $level) {
            if (in_array($level->getId()->toRfc4122(), $ids, true)) {
                return true;
            }
        }

        return false;
    }
}
