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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Stay|EducationalCentre|TrainingPosition>
 */
final class StayVoter extends Voter
{
    /** Ver una estancia en el listado y acceder a su contenido. Sujeto: Stay */
    public const VIEW   = 'stay.view';
    /** Gestionar una estancia completa (editar, eliminar, añadir puestos, gestionar estudiantes). Sujeto: Stay */
    public const MANAGE = 'stay.manage';
    /** Editar o eliminar un puesto formativo concreto. Sujeto: TrainingPosition */
    public const MANAGE_POSITION = 'stay.manage_position';
    /** Crear una nueva estancia en el centro activo. Sujeto: EducationalCentre */
    public const CREATE = 'stay.create';

    public function __construct(
        private readonly ProgrammeRepository $programmes,
        private readonly ProfessionalFamilyRepository $families,
        private readonly GroupRepository $groups,
        private readonly CompanyRepository $companies,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::VIEW, self::MANAGE => $subject instanceof Stay,
            self::CREATE             => $subject instanceof EducationalCentre,
            self::MANAGE_POSITION    => $subject instanceof TrainingPosition,
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
            self::MANAGE          => $this->canManage($user, $subject),
            self::MANAGE_POSITION => $this->canManagePosition($user, $subject),
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

        if ($this->programmes->isCoordinatorOf($user, $stay->getProgramme())) {
            return true;
        }

        if ($this->families->isFamilyHeadOfProgramme($user, $stay->getProgramme())) {
            return true;
        }

        if ($this->groups->isTeacherInProgramme($user, $stay->getProgramme())) {
            return true;
        }

        return $this->companies->hasLiaisonPositionInStay($user, $stay);
    }

    private function canManage(Teacher $user, Stay $stay): bool
    {
        $centre = $stay->getAcademicYear()->getEducationalCentre();

        if ($centre->getAdmins()->contains($user)) {
            return true;
        }

        if ($this->programmes->isCoordinatorOf($user, $stay->getProgramme())) {
            return true;
        }

        return $this->families->isFamilyHeadOfProgramme($user, $stay->getProgramme());
    }

    private function canManagePosition(Teacher $user, TrainingPosition $position): bool
    {
        $stay   = $position->getStay();
        $centre = $stay->getAcademicYear()->getEducationalCentre();

        if ($centre->getAdmins()->contains($user)) {
            return true;
        }

        if ($this->programmes->isCoordinatorOf($user, $stay->getProgramme())) {
            return true;
        }

        if ($this->families->isFamilyHeadOfProgramme($user, $stay->getProgramme())) {
            return true;
        }

        $workcenter = $position->getWorkcenter();

        return $workcenter !== null
            && $workcenter->getCompany()->getLiaisons()->contains($user);
    }

    private function canCreate(Teacher $user, EducationalCentre $centre): bool
    {
        if ($centre->getAdmins()->contains($user)) {
            return true;
        }

        return $this->programmes->isCoordinatorInCentre($user, $centre);
    }
}
