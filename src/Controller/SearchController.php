<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use App\Repository\StayRepository;
use App\Repository\StudentRepository;
use App\Repository\TeacherRepository;
use App\Entity\Teacher;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEACHER')]
class SearchController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StayRepository $stayRepository,
        private readonly StudentRepository $studentRepository,
        private readonly CompanyRepository $companyRepository,
        private readonly TeacherRepository $teacherRepository,
        private readonly RateLimiterFactoryInterface $searchLimiter,
    ) {}

    #[Route('/buscar', name: 'app_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $limiter = $this->searchLimiter->create($this->getUser()?->getUserIdentifier() ?? $request->getClientIp() ?? 'anon');
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['groups' => []], JsonResponse::HTTP_TOO_MANY_REQUESTS);
        }

        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->json(['groups' => []]);
        }

        $year = $centre->getActiveAcademicYear();
        if ($year === null) {
            return $this->json(['groups' => []]);
        }

        $q = trim($request->query->getString('q'));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 100) {
            return $this->json(['groups' => []]);
        }

        $user   = $this->getUser();
        $viewer = $user instanceof Teacher ? $user : null;

        $groups = [];

        // Stays — always (viewer-filtered)
        $stays = $this->stayRepository->searchByYearForViewer($year, $q, $viewer);
        if ($stays !== []) {
            $groups['stays'] = array_map(fn ($s) => [
                'label'    => $s->getName(),
                'sublabel' => $s->getProgramme()->getName(),
                'url'      => $this->generateUrl('app_stays_show', ['id' => $s->getId()->toRfc4122()]),
            ], $stays);
        }

        // Companies — if section permission
        if ($this->isGranted('company.section', $centre)) {
            $companies = $this->companyRepository->searchByCentre($centre, $q);
            if ($companies !== []) {
                $groups['companies'] = array_map(fn ($c) => [
                    'label'    => $c->getName(),
                    'sublabel' => $c->getCity(),
                    'url'      => $this->generateUrl('app_companies_edit', ['id' => $c->getId()->toRfc4122()]),
                ], $companies);
            }
        }

        // Students and teachers — if educational centre section permission
        if ($this->isGranted('educational_centre.section', $centre)) {
            $students = $this->studentRepository->searchByCentre($centre, $q);
            if ($students !== []) {
                $groups['students'] = array_map(fn ($s) => [
                    'label'    => $s->getName()->getLastName() . ', ' . $s->getName()->getFirstName(),
                    'sublabel' => $s->getStudentId(),
                    'url'      => $this->generateUrl('app_admin_students_edit', [
                        'centreId' => $centre->getId()->toRfc4122(),
                        'id'       => $s->getId()->toRfc4122(),
                    ]),
                ], $students);
            }

            $teachers = $this->teacherRepository->searchByAcademicYear($year, $q);
            if ($teachers !== []) {
                $groups['teachers'] = array_map(fn ($t) => [
                    'label'    => $t->getName()->getLastName() . ', ' . $t->getName()->getFirstName(),
                    'sublabel' => $t->getUsername(),
                    'url'      => $this->generateUrl('app_admin_centre_teachers_edit', [
                        'centreId'  => $centre->getId()->toRfc4122(),
                        'teacherId' => $t->getId()->toRfc4122(),
                    ]),
                ], $teachers);
            }
        }

        return $this->json(['groups' => $groups]);
    }
}
