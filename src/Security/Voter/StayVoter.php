<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\EducationalCentre;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Repository\CompanyRepository;
use App\Repository\GroupRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Security\StayScope;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Una estancia puede reunir varias enseñanzas. El reparto es:
 *
 * - **Ver** la estancia, todo su alumnado y todas las asignaciones: cualquiera que coordine, dirija
 *   (jefatura de familia) o imparta en alguna de sus enseñanzas.
 * - **Gestionar al alumnado** (matricular, asignar puesto, tutores): solo el de las enseñanzas que
 *   coordina o dirige cada docente (ver {@see StayScope}).
 * - **Puestos libres**: los gestiona cualquier coordinador de la estancia (son compartidos entre
 *   enseñanzas). Un puesto ya asignado lo gestiona quien gestiona a su estudiante.
 *
 * @extends Voter<string, Stay|EducationalCentre|TrainingPosition|PositionAssignment>
 */
final class StayVoter extends Voter
{
    /** Ver una estancia en el listado y acceder a su contenido. Sujeto: Stay */
    public const VIEW   = 'stay.view';
    /** Ver los puestos formativos sin estudiante asignado de una estancia. Sujeto: Stay */
    public const VIEW_UNASSIGNED = 'stay.view_unassigned';
    /** Gestionar la estancia (editarla, matricular a su propio alumnado). Sujeto: Stay */
    public const MANAGE = 'stay.manage';
    /** Eliminar la estancia: solo quien gestiona todas sus enseñanzas. Sujeto: Stay */
    public const DELETE = 'stay.delete';
    /** Añadir un nuevo puesto formativo a la estancia. Sujeto: Stay */
    public const ADD_POSITION = 'stay.add_position';
    /** Editar o eliminar un puesto formativo concreto. Sujeto: TrainingPosition */
    public const MANAGE_POSITION = 'stay.manage_position';
    /** Asignar un puesto a un estudiante. Sujeto: PositionAssignment */
    public const ASSIGN = 'stay.assign';
    /** Crear una nueva estancia en el centro activo. Sujeto: EducationalCentre */
    public const CREATE = 'stay.create';

    public function __construct(
        private readonly ProgrammeRepository $programmes,
        private readonly GroupRepository $groups,
        private readonly CompanyRepository $companies,
        private readonly StayScope $scope,
        private readonly ProfessionalFamilyRepository $families,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::VIEW, self::VIEW_UNASSIGNED, self::MANAGE, self::DELETE, self::ADD_POSITION => $subject instanceof Stay,
            self::CREATE                                  => $subject instanceof EducationalCentre,
            self::MANAGE_POSITION                         => $subject instanceof TrainingPosition,
            self::ASSIGN                                  => $subject instanceof PositionAssignment,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Teacher) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return match ($attribute) {
            self::VIEW            => $this->canView($user, $subject),
            self::VIEW_UNASSIGNED => $this->canViewUnassigned($user, $subject),
            self::MANAGE          => $this->canManage($user, $subject),
            self::DELETE          => $this->canDelete($user, $subject),
            self::ADD_POSITION    => $this->canAddPosition($user, $subject),
            self::MANAGE_POSITION => $this->canManagePosition($user, $subject),
            self::ASSIGN          => $this->canAssign($user, $subject),
            self::CREATE          => $this->canCreate($user, $subject),
            default => false,
        };
    }

    private function canView(Teacher $user, Stay $stay): bool
    {
        $centre = $stay->getAcademicYear()->getEducationalCentre();

        if ($centre->getAdmins()->contains($user)) {
            return true;
        }

        if ($this->scope->canManageAny($user, $stay)) {
            return true;
        }

        if ($this->groups->isTeacherInStayProgrammes($user, $stay)) {
            return true;
        }

        return $this->companies->hasLiaisonPositionInStay($user, $stay);
    }

    /**
     * Los puestos formativos sin estudiante (puestos «libres») solo son visibles
     * para quienes gestionan la asignación: dirección, coordinación, jefatura de
     * familia y los docentes de enlace del centro. Un docente o tutor de grupo ve
     * la estancia, pero no la bolsa de puestos libres.
     */
    private function canViewUnassigned(Teacher $user, Stay $stay): bool
    {
        if ($this->canManage($user, $stay)) {
            return true;
        }

        $centre = $stay->getAcademicYear()->getEducationalCentre();

        return $this->companies->hasLiaisonInCentre($user, $centre);
    }

    private function canManage(Teacher $user, Stay $stay): bool
    {
        return $this->scope->canManageAny($user, $stay);
    }

    /**
     * Eliminar exige gestionar todas las enseñanzas de la estancia: con una sola enseñanza
     * coincide con «gestionar»; con varias, evita que una coordinación borre lo de las demás.
     */
    private function canDelete(Teacher $user, Stay $stay): bool
    {
        if ($this->scope->manageableProgrammeIds($user, $stay) === null) {
            return true;
        }

        foreach ($stay->getProgrammes() as $programme) {
            if (!$this->scope->canManageProgramme($user, $stay, $programme)) {
                return false;
            }
        }

        return $stay->getProgrammes()->count() > 0;
    }

    private function canAddPosition(Teacher $user, Stay $stay): bool
    {
        if ($this->canManage($user, $stay)) {
            return true;
        }

        $centre = $stay->getAcademicYear()->getEducationalCentre();

        return $this->companies->hasLiaisonInCentre($user, $centre);
    }

    private function canManagePosition(Teacher $user, TrainingPosition $position): bool
    {
        $stay = $position->getStay();

        $student = $position->getStudent();
        if ($student === null) {
            // Puesto libre, compartido entre enseñanzas: cualquier coordinación de la estancia.
            if ($this->scope->canManageAny($user, $stay)) {
                return true;
            }
        } elseif ($this->scope->canManageStudent($user, $stay, $student)) {
            return true;
        }

        $workcenter = $position->getWorkcenter();

        return $workcenter !== null
            && $student === null
            && $workcenter->getCompany()->getLiaisons()->contains($user);
    }

    /**
     * Asignar exige gestionar al estudiante (su coordinación o jefatura de familia), salvo el
     * docente de enlace de la empresa, que puede asignar sus puestos libres como hasta ahora.
     */
    private function canAssign(Teacher $user, PositionAssignment $assignment): bool
    {
        $position = $assignment->position;

        if ($this->scope->canManageStudent($user, $position->getStay(), $assignment->student)) {
            return true;
        }

        $workcenter = $position->getWorkcenter();

        return $workcenter !== null
            && $position->getStudent() === null
            && $workcenter->getCompany()->getLiaisons()->contains($user);
    }

    private function canCreate(Teacher $user, EducationalCentre $centre): bool
    {
        if ($centre->getAdmins()->contains($user)) {
            return true;
        }

        if ($this->programmes->isCoordinatorInCentre($user, $centre)) {
            return true;
        }

        return $this->families->isFamilyHeadInCentre($user, $centre);
    }
}
