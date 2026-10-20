<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\StudentYearProfile;
use App\Entity\Teacher;
use App\Repository\StudentRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Ficha del estudiante en un curso (NUSS, FFEOE, coche, empresa aportada, actitud, localidades).
 *
 * Los datos los ven quienes ven al estudiante; este voter decide quién los **edita** y quién ve la
 * **actitud**, una valoración personal que no debe llegar a docentes de enlace ni a cualquier docente:
 * administración del centro y global, coordinación de las enseñanzas del estudiante, jefatura de su
 * familia y tutoría de sus grupos.
 *
 * Un curso que ya no es el activo del centro es de solo lectura para la edición.
 *
 * @extends Voter<string, StudentYearProfile>
 */
final class StudentProfileVoter extends Voter
{
    /** Editar la ficha del estudiante en el curso. Sujeto: StudentYearProfile */
    public const EDIT = 'student_profile.edit';
    /** Ver la actitud del estudiante. Sujeto: StudentYearProfile */
    public const VIEW_ATTITUDE = 'student_profile.view_attitude';

    public function __construct(private readonly StudentRepository $students) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::VIEW_ATTITUDE], true) && $subject instanceof StudentYearProfile;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Teacher) {
            return false;
        }

        $year   = $subject->getAcademicYear();
        $centre = $year->getEducationalCentre();

        if ($attribute === self::EDIT) {
            $active = $centre->getActiveAcademicYear();
            if ($active !== null && $active->getId()->toRfc4122() !== $year->getId()->toRfc4122()) {
                return false;
            }
        }

        if ($user->isAdmin() || $centre->getAdmins()->contains($user)) {
            return true;
        }

        return $this->students->isResponsibleInYear($user, $subject->getStudent(), $year);
    }
}
