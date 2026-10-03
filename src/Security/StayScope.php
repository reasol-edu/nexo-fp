<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Programme;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Repository\ProgrammeRepository;

/**
 * Reparto de responsabilidades dentro de una estancia con varias enseñanzas.
 *
 * Un docente gestiona a los estudiantes de las enseñanzas de la estancia que coordina o cuya
 * familia profesional dirige; la dirección del centro y la administración global los gestionan
 * todos. Quien no gestiona a un estudiante puede verlo (y ver su puesto), pero no modificarlo.
 *
 * Se cachea por docente y estancia durante la petición: la pantalla de una estancia consulta
 * el alcance de cada estudiante y no debe lanzar una consulta por cada uno.
 */
final class StayScope
{
    /** @var array<string, list<string>|null> ids de enseñanzas gestionables (null = todas), por «docente|estancia» */
    private array $cache = [];

    public function __construct(private readonly ProgrammeRepository $programmes) {}

    /**
     * Ids (RFC 4122) de las enseñanzas de la estancia cuyos estudiantes gestiona el docente,
     * o null si los gestiona todos (administración global o dirección del centro).
     *
     * @return list<string>|null
     */
    public function manageableProgrammeIds(Teacher $user, Stay $stay): ?array
    {
        $key = $user->getId()->toRfc4122() . '|' . $stay->getId()->toRfc4122();
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if ($user->isAdmin() || $stay->getAcademicYear()->getEducationalCentre()->getAdmins()->contains($user)) {
            return $this->cache[$key] = null;
        }

        $ids = [];
        foreach ($this->programmes->findCoordinatedByInStay($user, $stay) as $programme) {
            $ids[$programme->getId()->toRfc4122()] = true;
        }
        foreach ($this->programmes->findHeadedByInStay($user, $stay) as $programme) {
            $ids[$programme->getId()->toRfc4122()] = true;
        }

        return $this->cache[$key] = array_keys($ids);
    }

    /** ¿Gestiona el docente al menos una enseñanza de la estancia? */
    public function canManageAny(Teacher $user, Stay $stay): bool
    {
        $ids = $this->manageableProgrammeIds($user, $stay);

        return $ids === null || $ids !== [];
    }

    public function canManageProgramme(Teacher $user, Stay $stay, Programme $programme): bool
    {
        $ids = $this->manageableProgrammeIds($user, $stay);

        return $ids === null || in_array($programme->getId()->toRfc4122(), $ids, true);
    }

    /**
     * ¿Gestiona el docente a este estudiante en la estancia? Un estudiante sin grupo en
     * ninguna enseñanza de la estancia (p. ej. si cambió de grupo tras matricularse) lo
     * gestiona cualquiera que gestione alguna enseñanza, para que nunca quede huérfano.
     */
    public function canManageStudent(Teacher $user, Stay $stay, Student $student): bool
    {
        $ids = $this->manageableProgrammeIds($user, $stay);
        if ($ids === null) {
            return true;
        }
        if ($ids === []) {
            return false;
        }

        $studentProgrammes = $stay->getProgrammesOfStudent($student);
        if ($studentProgrammes === []) {
            return true;
        }

        foreach ($studentProgrammes as $programme) {
            if (in_array($programme->getId()->toRfc4122(), $ids, true)) {
                return true;
            }
        }

        return false;
    }
}
