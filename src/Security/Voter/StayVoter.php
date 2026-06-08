<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\EducationalCentre;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Repository\CompanyRepository;
use App\Repository\GroupRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Stay|EducationalCentre>
 */
final class StayVoter extends Voter
{
    /** Ver una estancia en el listado y acceder a su contenido. Sujeto: Stay */
    public const VIEW   = 'stay.view';
    /** Gestionar una estancia existente y sus puestos. Sujeto: Stay */
    public const MANAGE = 'stay.manage';
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
            self::VIEW   => $this->canView($user, $subject),
            self::MANAGE => $this->canManage($user, $subject),
            self::CREATE => $this->canCreate($user, $subject),
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

        return $this->companies->hasLiaisonInCentre($user, $centre);
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

        if ($this->families->isFamilyHeadOfProgramme($user, $stay->getProgramme())) {
            return true;
        }

        return $this->companies->hasLiaisonInCentre($user, $centre);
    }

    private function canCreate(Teacher $user, EducationalCentre $centre): bool
    {
        if ($centre->getAdmins()->contains($user)) {
            return true;
        }

        return $this->programmes->isCoordinatorInCentre($user, $centre);
    }
}
