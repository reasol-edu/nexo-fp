<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Repository\CompanyRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\ProgrammeYearRepository;
use App\Repository\StayRepository;
use App\Repository\TrainingPositionRepository;
use App\Repository\WorkcenterRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/estancias')]
#[IsGranted('ROLE_TEACHER')]
class StayController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenant,
        private readonly StayRepository $stays,
        private readonly TrainingPositionRepository $positions,
        private readonly CompanyRepository $companies,
        private readonly WorkcenterRepository $workcenters,
        private readonly ProgrammeYearRepository $programmeYears,
        private readonly ProfessionalFamilyRepository $families,
        private readonly ProgrammeRepository $programmes,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_stays_index')]
    public function index(): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        return $this->render('stays/index.html.twig', ['centre' => $centre]);
    }

    #[Route('/nueva', name: 'app_stays_new')]
    public function new(Request $request): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $year = $centre->getActiveAcademicYear();
        if ($year === null) {
            $this->addFlash('error', $this->t('stays.flash.no_active_year'));

            return $this->redirectToRoute('app_stays_index');
        }

        $allProgrammes = $this->programmes->findByAcademicYearOrderedByFamilyAndName($year);

        /** @var array<string, array{family: \App\Entity\ProfessionalFamily, programmes: \App\Entity\Programme[]}> $byFamily */
        $byFamily = [];
        foreach ($allProgrammes as $p) {
            $fid = $p->getProfessionalFamily()->getId()->toRfc4122();
            if (!isset($byFamily[$fid])) {
                $byFamily[$fid] = ['family' => $p->getProfessionalFamily(), 'programmes' => []];
            }
            $byFamily[$fid]['programmes'][] = $p;
        }

        $errors = [];
        $values = ['name' => '', 'programme_id' => '', 'start_date' => '', 'end_date' => ''];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_stay', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'name'         => trim($request->request->getString('name')),
                'programme_id' => trim($request->request->getString('programme_id')),
                'start_date'   => trim($request->request->getString('start_date')),
                'end_date'     => trim($request->request->getString('end_date')),
            ];

            if ($values['name'] === '') {
                $errors['name'] = $this->t('stays.error.name_required');
            } elseif ($this->stays->existsByNameAndYear($values['name'], $year)) {
                $errors['name'] = $this->t('stays.error.name_duplicate');
            }

            $programme = null;
            if ($values['programme_id'] !== '') {
                $programme = $this->programmes->findByAcademicYearAndId($year, $values['programme_id']);
            }
            if ($programme === null) {
                $errors['programme_id'] = $this->t('stays.error.programme_required');
            }

            $startDate = null;
            if ($values['start_date'] === '') {
                $errors['start_date'] = $this->t('stays.error.date_required');
            } else {
                $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', $values['start_date']);
                if ($startDate === false) {
                    $errors['start_date'] = $this->t('stays.error.date_invalid');
                    $startDate = null;
                }
            }

            $endDate = null;
            if ($values['end_date'] === '') {
                $errors['end_date'] = $this->t('stays.error.date_required');
            } else {
                $endDate = \DateTimeImmutable::createFromFormat('Y-m-d', $values['end_date']);
                if ($endDate === false) {
                    $errors['end_date'] = $this->t('stays.error.date_invalid');
                    $endDate = null;
                } elseif ($startDate !== null && $endDate < $startDate) {
                    $errors['end_date'] = $this->t('stays.error.end_before_start');
                    $endDate = null;
                }
            }

            if (empty($errors) && $programme !== null && $startDate !== null && $endDate !== null) {
                $stay = new Stay();
                $stay->setName($values['name'])
                     ->setAcademicYear($year)
                     ->setProgramme($programme)
                     ->setStartDate($startDate)
                     ->setEndDate($endDate);

                $this->em->persist($stay);
                $this->em->flush();

                $this->addFlash('success', $this->t('stays.flash.created'));

                return $this->redirectToRoute('app_stays_index');
            }
        }

        return $this->render('stays/new.html.twig', [
            'centre'    => $centre,
            'by_family' => $byFamily,
            'errors'    => $errors,
            'values'    => $values,
        ]);
    }

    #[Route('/{id}', name: 'app_stays_show')]
    public function show(string $id): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $stay = $this->stays->findById($id);
        $year = $centre->getActiveAcademicYear();

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $trainingPositions = $this->positions->findByStayOrdered($stay);

        $assignedIds = [];
        foreach ($trainingPositions as $tp) {
            if ($tp->getStudent() !== null) {
                $assignedIds[$tp->getStudent()->getId()->toRfc4122()] = true;
            }
        }

        $unassigned = array_values(
            $stay->getStudents()
                 ->filter(fn ($s) => !isset($assignedIds[$s->getId()->toRfc4122()]))
                 ->toArray()
        );
        usort($unassigned, static fn ($a, $b) =>
            $a->getName()->getLastName() <=> $b->getName()->getLastName()
            ?: $a->getName()->getFirstName() <=> $b->getName()->getFirstName()
        );

        $statsMap = $this->stays->findStatsForStays([$stay]);
        $stats    = $statsMap[$stay->getId()->toRfc4122()] ?? [];

        return $this->render('stays/show.html.twig', [
            'centre'     => $centre,
            'stay'       => $stay,
            'positions'  => $trainingPositions,
            'unassigned' => $unassigned,
            'stats'      => $stats,
            'can_manage' => $this->canManagePositions($stay, $centre),
        ]);
    }

    #[Route('/{id}/nuevo-puesto', name: 'app_stays_new_position')]
    public function newPosition(string $id, Request $request): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $stay = $this->stays->findById($id);
        $year = $centre->getActiveAcademicYear();

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        if (!$this->canManagePositions($stay, $centre)) {
            throw $this->createAccessDeniedException();
        }

        $allWorkcenters = $this->workcenters->findByCentreOrdered($centre);
        $byCompany      = [];
        foreach ($allWorkcenters as $wc) {
            $cid = $wc->getCompany()->getId()->toRfc4122();
            if (!isset($byCompany[$cid])) {
                $byCompany[$cid] = ['company' => $wc->getCompany(), 'workcenters' => []];
            }
            $byCompany[$cid]['workcenters'][] = $wc;
        }

        $programmeYears = $this->programmeYears->findByProgrammeOrderedByName($stay->getProgramme());

        $errors = [];
        $values = ['workcenter_id' => '', 'programme_year_ids' => [], 'details' => '', 'count' => '1'];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_position_' . $id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'workcenter_id'      => trim($request->request->getString('workcenter_id')),
                'programme_year_ids' => $request->request->all('programme_year_ids'),
                'details'            => trim($request->request->getString('details')),
                'count'              => trim($request->request->getString('count')),
            ];

            $count = filter_var($values['count'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
            if ($count === false) {
                $errors['count'] = $this->t('stays.error.position_count_invalid');
                $count = 1;
            }

            $workcenter = null;
            if ($values['workcenter_id'] !== '') {
                $workcenter = $this->workcenters->findByCentreAndId($centre, $values['workcenter_id']);
                if ($workcenter === null) {
                    $errors['workcenter_id'] = $this->t('stays.error.workcenter_invalid');
                }
            }

            $selectedYears = [];
            foreach ($values['programme_year_ids'] as $pyId) {
                $py = $this->programmeYears->findByProgrammeAndId($stay->getProgramme(), (string) $pyId);
                if ($py !== null) {
                    $selectedYears[] = $py;
                }
            }

            if (empty($errors)) {
                for ($i = 0; $i < $count; $i++) {
                    $position = new TrainingPosition();
                    $position->setStay($stay)
                             ->setWorkcenter($workcenter)
                             ->setDetails($values['details'] !== '' ? $values['details'] : null);
                    foreach ($selectedYears as $py) {
                        $position->addProgrammeYear($py);
                    }
                    $this->em->persist($position);
                }
                $this->em->flush();

                $this->addFlash('success', $this->translator->trans(
                    'stays.flash.positions_created',
                    ['%count%' => $count],
                    'stays'
                ));

                return $this->redirectToRoute('app_stays_show', ['id' => $id]);
            }
        }

        return $this->render('stays/new_position.html.twig', [
            'centre'          => $centre,
            'stay'            => $stay,
            'by_company'      => $byCompany,
            'programme_years' => $programmeYears,
            'errors'          => $errors,
            'values'          => $values,
        ]);
    }

    private function canManagePositions(Stay $stay, \App\Entity\EducationalCentre $centre): bool
    {
        /** @var Teacher $teacher */
        $teacher = $this->getUser();

        if ($teacher->isAdmin()) {
            return true;
        }

        $teacherId = $teacher->getId()->toRfc4122();

        foreach ($centre->getAdmins() as $admin) {
            if ($admin->getId()->toRfc4122() === $teacherId) {
                return true;
            }
        }

        if ($this->programmes->isCoordinatorOf($teacher, $stay->getProgramme())) {
            return true;
        }

        if ($this->companies->hasLiaisonInCentre($teacher, $centre)) {
            return true;
        }

        return false;
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'stays');
    }
}
