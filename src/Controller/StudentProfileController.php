<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\StudentYearProfile;
use App\Repository\StudentRepository;
use App\Repository\StudentYearProfileRepository;
use App\Security\Voter\StudentProfileVoter;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ficha del estudiante en el curso (NUSS, FFEOE, coche, empresa aportada, actitud, localidades).
 * Se abre como diálogo desde las listas (petición XHR: solo el formulario) y también funciona como
 * página completa. Quién edita lo decide {@see StudentProfileVoter}.
 */
#[Route('/estudiantes/{id}/ficha', name: 'app_student_profile', methods: ['GET', 'POST'])]
final class StudentProfileController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly StudentRepository $students,
        private readonly StudentYearProfileRepository $profiles,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {}

    public function __invoke(string $id, Request $request): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        $year   = $centre !== null ? $this->tenant->getViewYear($centre) : null;
        $student = Uuid::isValid($id) ? $this->students->findById($id) : null;
        if ($year === null || $student === null || !$this->students->isInYear($student, $year)) {
            throw $this->createNotFoundException();
        }

        $profile = $this->profiles->findByStudentAndYear($student, $year) ?? new StudentYearProfile($student, $year);
        $this->denyAccessUnlessGranted(StudentProfileVoter::EDIT, $profile);

        $ajax   = $request->isXmlHttpRequest();
        $errors = [];
        $values = $this->valuesOf($profile);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('student_profile_' . $id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = $this->readValues($request);
            $errors = $this->validate($values);

            if ($errors === []) {
                $this->apply($profile, $values);
                $this->em->persist($profile);
                $this->em->flush();

                if ($ajax) {
                    return new JsonResponse(['ok' => true]);
                }

                $this->addFlash('success', $this->translator->trans('student_profile.saved', ['%student%' => $student->getName()->full()], 'stays'));

                return $this->redirect($this->safeReturnUrl($request));
            }
        }

        $response = $this->render($ajax ? 'student_profile/_form.html.twig' : 'student_profile/page.html.twig', [
            'student'  => $student,
            'values'   => $values,
            'errors'   => $errors,
            'return'   => $this->safeReturnUrl($request),
            'inDialog' => $ajax,
        ]);

        return $errors !== [] ? $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY) : $response;
    }

    /** @return array<string, mixed> */
    private function valuesOf(StudentYearProfile $profile): array
    {
        return [
            'has_nuss'             => $profile->hasNuss(),
            'takes_ffeoe'          => $profile->takesFfeoe(),
            'exclusion_reason'     => $profile->getFfeoeExclusionReason() ?? '',
            'has_car'              => $profile->hasCar(),
            'brings_company'       => $profile->bringsCompany(),
            'company_name'         => $profile->getBroughtCompanyName() ?? '',
            'attitude'             => $profile->getAttitude() === null ? '' : (string) $profile->getAttitude(),
            'residence_city'       => $profile->getResidenceCity() ?? '',
            'preferred_city'       => $profile->getPreferredCity() ?? '',
        ];
    }

    /** @return array<string, mixed> */
    private function readValues(Request $request): array
    {
        $bool = static fn (string $name): ?bool => match ($request->request->getString($name)) {
            '1'     => true,
            '0'     => false,
            default => null,
        };

        return [
            'has_nuss'         => $bool('has_nuss'),
            'takes_ffeoe'      => $bool('takes_ffeoe') ?? true,
            'exclusion_reason' => mb_substr(trim($request->request->getString('exclusion_reason')), 0, 1000),
            'has_car'          => $bool('has_car') ?? false,
            'brings_company'   => $bool('brings_company') ?? false,
            'company_name'     => mb_substr(trim($request->request->getString('company_name')), 0, 255),
            'attitude'         => trim($request->request->getString('attitude')),
            'residence_city'   => mb_substr(trim($request->request->getString('residence_city')), 0, 255),
            'preferred_city'   => mb_substr(trim($request->request->getString('preferred_city')), 0, 255),
        ];
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, string>
     */
    private function validate(array $values): array
    {
        $errors = [];

        if ($values['takes_ffeoe'] === false && $values['exclusion_reason'] === '') {
            $errors['exclusion_reason'] = $this->translator->trans('student_profile.error.reason_required', [], 'stays');
        }
        if ($values['brings_company'] === true && $values['company_name'] === '') {
            $errors['company_name'] = $this->translator->trans('student_profile.error.company_required', [], 'stays');
        }
        if ($values['attitude'] !== '' && !\in_array($values['attitude'], ['1', '2', '3', '4', '5'], true)) {
            $errors['attitude'] = $this->translator->trans('student_profile.error.attitude_range', [], 'stays');
        }

        return $errors;
    }

    /** @param array<string, mixed> $values */
    private function apply(StudentYearProfile $profile, array $values): void
    {
        $profile
            ->setHasNuss($values['has_nuss'])
            ->setTakesFfeoe($values['takes_ffeoe'])
            ->setFfeoeExclusionReason($values['takes_ffeoe'] ? null : $values['exclusion_reason'])
            ->setHasCar($values['has_car'])
            ->setBringsCompany($values['brings_company'])
            ->setBroughtCompanyName($values['brings_company'] ? $values['company_name'] : null)
            ->setAttitude($values['attitude'] === '' ? null : (int) $values['attitude'])
            ->setResidenceCity($values['residence_city'])
            ->setPreferredCity($values['preferred_city']);
    }

    /** Solo se vuelve a rutas de la propia aplicación. */
    private function safeReturnUrl(Request $request): string
    {
        $return = $request->query->getString('return', $request->request->getString('return'));

        return str_starts_with($return, '/') && !str_starts_with($return, '//') && !str_contains($return, '\\')
            ? $return
            : $this->generateUrl('app_stays_index');
    }
}
