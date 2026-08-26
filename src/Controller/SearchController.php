<?php

namespace App\Controller;

use App\Entity\EducationalCentre;
use App\Repository\CompanyRepository;
use App\Repository\StayRepository;
use App\Repository\StudentRepository;
use App\Repository\TeacherRepository;
use App\Entity\Teacher;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_TEACHER')]
class SearchController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StayRepository $stayRepository,
        private readonly StudentRepository $studentRepository,
        private readonly CompanyRepository $companyRepository,
        private readonly TeacherRepository $teacherRepository,
        private readonly TranslatorInterface $translator,
        #[Target('search')]
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

        $year = $this->tenantContext->getViewYear($centre);
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

        // Navigation sections — matched by their translated label, same permissions as the sidebar
        $pages = $this->matchingPages($q, $centre);
        if ($pages !== []) {
            $groups['pages'] = $pages;
        }

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

    /**
     * Secciones de navegación cuya etiqueta traducida coincide con la búsqueda, respetando los mismos
     * permisos que muestran u ocultan cada entrada en el menú lateral (ver templates/layouts/app.html.twig).
     *
     * @return list<array{label: string, sublabel: null, url: string}>
     */
    private function matchingPages(string $q, EducationalCentre $centre): array
    {
        $candidates = [
            ['label' => $this->translator->trans('dashboard', [], 'navigation'), 'route' => 'app_dashboard'],
            ['label' => $this->translator->trans('stays', [], 'navigation'), 'route' => 'app_stays_index'],
            ['label' => $this->translator->trans('calendar', [], 'navigation'), 'route' => 'app_calendar'],
        ];

        if ($this->isGranted('company.section', $centre)) {
            $candidates[] = ['label' => $this->translator->trans('companies', [], 'navigation'), 'route' => 'app_companies_index'];
        }

        if ($this->isGranted('educational_centre.section', $centre)) {
            $candidates[] = ['label' => $this->translator->trans('educational_centre', [], 'navigation'), 'route' => 'app_educational_centre_index'];
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $candidates[] = ['label' => $this->translator->trans('administration', [], 'navigation'), 'route' => 'app_admin'];
        }

        $matches = array_values(array_filter(
            $candidates,
            fn (array $page) => mb_stripos($page['label'], $q) !== false,
        ));

        return array_map(fn (array $page) => [
            'label'    => $page['label'],
            'sublabel' => null,
            'url'      => $this->generateUrl($page['route']),
        ], $matches);
    }
}
