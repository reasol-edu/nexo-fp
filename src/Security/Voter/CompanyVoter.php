<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Teacher;
use App\Repository\CompanyRepository;
use App\Repository\ProfessionalFamilyRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Company|EducationalCentre>
 */
final class CompanyVoter extends Voter
{
    /** Acceso a la sección Empresas (lista + creación). Sujeto: EducationalCentre */
    public const SECTION = 'company.section';
    /** Editar/crear una empresa concreta. Sujeto: Company */
    public const EDIT = 'company.edit';
    /** Eliminar una empresa. Sujeto: Company */
    public const DELETE = 'company.delete';

    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly ProfessionalFamilyRepository $families,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::SECTION => $subject instanceof EducationalCentre,
            self::EDIT, self::DELETE => $subject instanceof Company,
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

        /** @var Company|EducationalCentre $subject */
        return match ($attribute) {
            self::SECTION => $this->canAccessSection($user, $subject),
            self::EDIT    => $this->canEdit($user, $subject),
            self::DELETE  => $this->canDelete($user, $subject),
            default => false,
        };
    }

    private function canAccessSection(Teacher $user, EducationalCentre $centre): bool
    {
        if ($centre->getAdmins()->contains($user)) {
            return true;
        }

        if ($this->companies->hasLiaisonInCentre($user, $centre)) {
            return true;
        }

        return $this->families->isFamilyHeadInCentre($user, $centre);
    }

    private function canEdit(Teacher $user, Company $company): bool
    {
        $centre = $company->getEducationalCentre();

        if ($centre->getAdmins()->contains($user)) {
            return true;
        }

        if ($company->getLiaisons()->contains($user)) {
            return true;
        }

        return $this->families->isFamilyHeadInCentre($user, $centre);
    }

    private function canDelete(Teacher $user, Company $company): bool
    {
        return $company->getEducationalCentre()->getAdmins()->contains($user);
    }
}
