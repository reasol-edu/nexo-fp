<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Repository\CompanyRepository;
use App\Repository\GroupRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\ProgrammeYearRepository;
use App\Repository\StayRepository;
use App\Repository\TeacherRepository;
use App\Repository\TrainingPositionRepository;
use App\Repository\WorkcenterRepository;
use App\Service\PdfService;
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
        private readonly GroupRepository $groups,
        private readonly ProfessionalFamilyRepository $families,
        private readonly ProgrammeRepository $programmes,
        private readonly TeacherRepository $teachers,
        private readonly TranslatorInterface $translator,
        private readonly PdfService $pdf,
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

    #[Route('/{id}/eliminar', name: 'app_stays_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
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

        if (!$this->isCsrfTokenValid('delete_stay_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        // Remove training positions first so Doctrine cleans their programmeYears join table.
        // Then remove the stay; Doctrine clears stay_students (Stay is the owning side).
        foreach ($this->positions->findByStayOrdered($stay) as $tp) {
            $this->em->remove($tp);
        }
        $this->em->remove($stay);
        $this->em->flush();

        $this->addFlash('success', $this->t('stays.flash.deleted'));

        return $this->redirectToRoute('app_stays_index');
    }

    #[Route('/{id}/editar', name: 'app_stays_edit')]
    public function edit(string $id, Request $request): Response
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

        $errors = [];
        $values = [
            'name'       => $stay->getName(),
            'start_date' => $stay->getStartDate()->format('Y-m-d'),
            'end_date'   => $stay->getEndDate()->format('Y-m-d'),
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_stay_' . $id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'name'       => trim($request->request->getString('name')),
                'start_date' => trim($request->request->getString('start_date')),
                'end_date'   => trim($request->request->getString('end_date')),
            ];

            if ($values['name'] === '') {
                $errors['name'] = $this->t('stays.error.name_required');
            } elseif ($this->stays->existsByNameAndYear($values['name'], $year, $stay)) {
                $errors['name'] = $this->t('stays.error.name_duplicate');
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

            if (empty($errors) && $startDate !== null && $endDate !== null) {
                $stay->setName($values['name'])
                     ->setStartDate($startDate)
                     ->setEndDate($endDate);

                $this->em->flush();

                $this->addFlash('success', $this->t('stays.flash.updated'));

                return $this->redirectToRoute('app_stays_show', ['id' => $id]);
            }
        }

        return $this->render('stays/edit.html.twig', [
            'centre' => $centre,
            'stay'   => $stay,
            'errors' => $errors,
            'values' => $values,
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

        return $this->render('stays/show.html.twig', [
            'centre' => $centre,
            'stay'   => $stay,
        ]);
    }

    #[Route('/{id}/informe', name: 'app_stays_report')]
    public function report(string $id): Response
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

        $allPositions = $this->positions->findByStayOrdered($stay);

        $studentPositionMap  = [];
        $unassignedPositions = [];
        foreach ($allPositions as $tp) {
            if ($tp->getStudent() !== null) {
                $studentPositionMap[$tp->getStudent()->getId()->toRfc4122()] = $tp;
            } else {
                $unassignedPositions[] = $tp;
            }
        }

        $enrolledStudents = [];
        foreach ($stay->getStudents() as $s) {
            $enrolledStudents[$s->getId()->toRfc4122()] = $s;
        }

        $byGroup          = [];
        $placedStudentIds = [];
        foreach ($this->groups->findByProgrammeWithStudents($stay->getProgramme()) as $group) {
            $groupStudents = [];
            foreach ($group->getStudents() as $student) {
                $sid = $student->getId()->toRfc4122();
                if (isset($enrolledStudents[$sid])) {
                    $groupStudents[]        = $student;
                    $placedStudentIds[$sid] = true;
                }
            }
            if ($groupStudents !== []) {
                $byGroup[] = ['group' => $group, 'students' => $groupStudents];
            }
        }

        $ungroupedStudents = [];
        foreach ($enrolledStudents as $sid => $student) {
            if (!isset($placedStudentIds[$sid])) {
                $ungroupedStudents[] = $student;
            }
        }
        usort($ungroupedStudents, fn ($a, $b) =>
            $a->getName()->getLastName() <=> $b->getName()->getLastName()
            ?: $a->getName()->getFirstName() <=> $b->getName()->getFirstName()
        );

        $statsMap = $this->stays->findStatsForStays([$stay]);
        $stats    = $statsMap[$stay->getId()->toRfc4122()] ?? [];

        $reportFilenameBase = $this->t('stays.report.filename_base');
        $slug               = preg_replace('/[^a-z0-9]+/i', '-', $stay->getName()) ?? $reportFilenameBase;
        $filename           = $reportFilenameBase . '-' . strtolower($slug) . '-' . (new \DateTimeImmutable())->format('Y-m-d') . '.pdf';

        return $this->pdf->renderPdf('pdf/stay_report.html.twig', [
            'stay'                 => $stay,
            'centre'               => $centre,
            'academic_year'        => $year,
            'stats'                => $stats,
            'by_group'             => $byGroup,
            'ungrouped_students'   => $ungroupedStudents,
            'student_position_map' => $studentPositionMap,
            'unassigned_positions' => $unassignedPositions,
        ], $filename);
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
            if ($values['workcenter_id'] === '') {
                $errors['workcenter_id'] = $this->t('stays.error.workcenter_required');
            } else {
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
            if ($programmeYears !== [] && $selectedYears === []) {
                $errors['programme_year_ids'] = $this->t('stays.error.programme_year_required');
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

    #[Route('/{id}/puesto/{positionId}/eliminar', name: 'app_stays_delete_position', methods: ['POST'])]
    public function deletePosition(string $id, string $positionId, Request $request): Response
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

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('delete_position_' . $positionId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->em->remove($position);
        $this->em->flush();

        $this->addFlash('success', $this->t('stays.flash.position_deleted'));

        return $this->redirectToRoute('app_stays_show', ['id' => $id]);
    }

    #[Route('/{id}/puesto/{positionId}/editar', name: 'app_stays_edit_position')]
    public function editPosition(string $id, string $positionId, Request $request): Response
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

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null) {
            throw $this->createNotFoundException();
        }

        // Workcenters for autocomplete
        $allWorkcenters = $this->workcenters->findByCentreOrdered($centre);
        $byCompany = [];
        foreach ($allWorkcenters as $wc) {
            $cid = $wc->getCompany()->getId()->toRfc4122();
            if (!isset($byCompany[$cid])) {
                $byCompany[$cid] = ['company' => $wc->getCompany(), 'workcenters' => []];
            }
            $byCompany[$cid]['workcenters'][] = $wc;
        }

        // Workers by company for the mentor select
        $workersByCompany = [];
        foreach ($byCompany as $cid => $entry) {
            $workers = $entry['company']->getWorkers()->toArray();
            usort($workers, fn ($a, $b) =>
                $a->getName()->getLastName() <=> $b->getName()->getLastName()
                ?: $a->getName()->getFirstName() <=> $b->getName()->getFirstName()
            );
            $workersByCompany[$cid] = $workers;
        }

        // Ensure the current mentor is available even if their company has no workcenters in this centre
        $currentMentor = $position->getWorkplaceMentor();
        if ($currentMentor !== null) {
            $found = false;
            foreach ($workersByCompany as $workers) {
                foreach ($workers as $w) {
                    if ($w->getId()->toRfc4122() === $currentMentor->getId()->toRfc4122()) {
                        $found = true;
                        break 2;
                    }
                }
            }
            if (!$found) {
                $workersByCompany['__extra'] = [$currentMentor];
            }
        }

        $programmeYears = $this->programmeYears->findByProgrammeOrderedByName($stay->getProgramme());

        // Only teachers who teach in the programme's groups
        $teachers = $this->teachers->findByProgrammeOrderedByName($stay->getProgramme());
        // Ensure the current tutor is in the list even if they no longer teach in the programme
        $currentTutor = $position->getAcademicTutor();
        if ($currentTutor !== null) {
            $teacherIds = array_map(fn ($t) => $t->getId()->toRfc4122(), $teachers);
            if (!\in_array($currentTutor->getId()->toRfc4122(), $teacherIds, true)) {
                array_unshift($teachers, $currentTutor);
            }
        }

        // Enrolled students in the stay
        $enrolledStudents = [];
        foreach ($stay->getStudents() as $s) {
            $enrolledStudents[$s->getId()->toRfc4122()] = $s;
        }
        uasort($enrolledStudents, fn ($a, $b) =>
            $a->getName()->getLastName() <=> $b->getName()->getLastName()
            ?: $a->getName()->getFirstName() <=> $b->getName()->getFirstName()
        );

        // Student → group map (group within the programme)
        $studentGroupMap = [];
        foreach ($this->groups->findByProgrammeWithStudents($stay->getProgramme()) as $group) {
            foreach ($group->getStudents() as $s) {
                $sid = $s->getId()->toRfc4122();
                if (isset($enrolledStudents[$sid]) && !isset($studentGroupMap[$sid])) {
                    $studentGroupMap[$sid] = $group;
                }
            }
        }

        // Students assigned to another position in this stay
        $otherAssignedIds = [];
        foreach ($this->positions->findByStayOrdered($stay) as $tp) {
            if ($tp->getStudent() !== null
                && $tp->getId()->toRfc4122() !== $position->getId()->toRfc4122()
            ) {
                $otherAssignedIds[$tp->getStudent()->getId()->toRfc4122()] = true;
            }
        }

        $currentPyIds = array_map(
            fn ($py) => $py->getId()->toRfc4122(),
            $position->getProgrammeYears()->toArray()
        );

        $errors = [];
        $values = [
            'workcenter_id'       => $position->getWorkcenter()?->getId()->toRfc4122() ?? '',
            'programme_year_ids'  => $currentPyIds,
            'details'             => $position->getDetails() ?? '',
            'student_id'          => $position->getStudent()?->getId()->toRfc4122() ?? '',
            'academic_tutor_id'   => $position->getAcademicTutor()?->getId()->toRfc4122() ?? '',
            'workplace_mentor_id' => $position->getWorkplaceMentor()?->getId()->toRfc4122() ?? '',
            'state'               => $position->getState()->value,
            'signed'              => $position->isSigned(),
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_position_' . $positionId, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'workcenter_id'       => trim($request->request->getString('workcenter_id')),
                'programme_year_ids'  => $request->request->all('programme_year_ids'),
                'details'             => trim($request->request->getString('details')),
                'student_id'          => trim($request->request->getString('student_id')),
                'academic_tutor_id'   => trim($request->request->getString('academic_tutor_id')),
                'workplace_mentor_id' => trim($request->request->getString('workplace_mentor_id')),
                'state'               => trim($request->request->getString('state')),
                'signed'              => $request->request->has('signed'),
            ];

            // Validate workcenter
            $workcenter = null;
            if ($values['workcenter_id'] === '') {
                $errors['workcenter_id'] = $this->t('stays.error.workcenter_required');
            } else {
                $workcenter = $this->workcenters->findByCentreAndId($centre, $values['workcenter_id']);
                if ($workcenter === null) {
                    $errors['workcenter_id'] = $this->t('stays.error.workcenter_invalid');
                }
            }

            // Validate programme years
            $selectedYears = [];
            foreach ($values['programme_year_ids'] as $pyId) {
                $py = $this->programmeYears->findByProgrammeAndId($stay->getProgramme(), (string) $pyId);
                if ($py !== null) {
                    $selectedYears[] = $py;
                }
            }
            if ($programmeYears !== [] && $selectedYears === []) {
                $errors['programme_year_ids'] = $this->t('stays.error.programme_year_required');
            }

            // Validate student (optional)
            $student = null;
            if ($values['student_id'] !== '') {
                if (isset($enrolledStudents[$values['student_id']])) {
                    if (isset($otherAssignedIds[$values['student_id']])) {
                        $errors['student_id'] = $this->t('stays.error.student_already_assigned');
                    } else {
                        $student = $enrolledStudents[$values['student_id']];
                    }
                } else {
                    $errors['student_id'] = $this->t('stays.error.student_invalid');
                }
            }

            // Validate academic tutor (optional)
            $academicTutor = null;
            if ($values['academic_tutor_id'] !== '') {
                $academicTutor = $this->teachers->findById($values['academic_tutor_id']);
                if ($academicTutor === null) {
                    $errors['academic_tutor_id'] = $this->t('stays.error.tutor_invalid');
                }
            }

            // Validate workplace mentor (optional)
            $workplaceMentor = null;
            if ($values['workplace_mentor_id'] !== '') {
                $found = false;
                foreach ($workersByCompany as $workers) {
                    foreach ($workers as $w) {
                        if ($w->getId()->toRfc4122() === $values['workplace_mentor_id']) {
                            $workplaceMentor = $w;
                            $found = true;
                            break 2;
                        }
                    }
                }
                if (!$found) {
                    $errors['workplace_mentor_id'] = $this->t('stays.error.mentor_invalid');
                }
            }

            $state = TrainingPositionState::tryFrom($values['state']) ?? TrainingPositionState::DRAFT;

            if ($values['signed'] && $state !== TrainingPositionState::DONE) {
                $errors['signed'] = $this->t('stays.error.signed_requires_done');
            }

            if (empty($errors)) {
                // Sync programme years
                foreach ($position->getProgrammeYears()->toArray() as $py) {
                    $position->removeProgrammeYear($py);
                }
                foreach ($selectedYears as $py) {
                    $position->addProgrammeYear($py);
                }

                $position->setWorkcenter($workcenter)
                         ->setDetails($values['details'] !== '' ? $values['details'] : null)
                         ->setStudent($student)
                         ->setAcademicTutor($academicTutor)
                         ->setWorkplaceMentor($workplaceMentor)
                         ->setState($state)
                         ->setSigned($values['signed']);

                $this->em->flush();

                $this->addFlash('success', $this->t('stays.flash.position_updated'));

                return $this->redirectToRoute('app_stays_show', ['id' => $id]);
            }
        }

        return $this->render('stays/edit_position.html.twig', [
            'centre'              => $centre,
            'stay'                => $stay,
            'position'            => $position,
            'by_company'          => $byCompany,
            'workers_by_company'  => $workersByCompany,
            'programme_years'     => $programmeYears,
            'teachers'            => $teachers,
            'enrolled_students'   => $enrolledStudents,
            'student_group_map'   => $studentGroupMap,
            'other_assigned_ids'  => $otherAssignedIds,
            'errors'              => $errors,
            'values'              => $values,
        ]);
    }

    #[Route('/{id}/estudiantes', name: 'app_stays_manage_students')]
    public function manageStudents(string $id, Request $request): Response
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

        // Groups with students, eager-loaded, for the stay's programme
        $groupList = $this->groups->findByProgrammeWithStudents($stay->getProgramme());

        // Organize by ProgrammeYear for template
        $byLevel = [];
        $eligibleStudents = [];
        foreach ($groupList as $group) {
            $pyId = $group->getProgrammeYear()->getId()->toRfc4122();
            if (!isset($byLevel[$pyId])) {
                $byLevel[$pyId] = ['level' => $group->getProgrammeYear(), 'groups' => []];
            }
            $byLevel[$pyId]['groups'][] = $group;
            foreach ($group->getStudents() as $student) {
                $eligibleStudents[$student->getId()->toRfc4122()] = $student;
            }
        }

        // Currently enrolled students
        $enrolledStudents = [];
        foreach ($stay->getStudents() as $student) {
            $enrolledStudents[$student->getId()->toRfc4122()] = $student;
        }

        // Students who have a training position in this stay → cannot be removed
        $hasPositionIds = [];
        foreach ($this->positions->findByStayOrdered($stay) as $tp) {
            if ($tp->getStudent() !== null) {
                $hasPositionIds[$tp->getStudent()->getId()->toRfc4122()] = true;
            }
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('manage_students_' . $id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $submittedIds = [];
            foreach ($request->request->all('student_ids') as $sid) {
                $sid = (string) $sid;
                if (isset($eligibleStudents[$sid])) {
                    $submittedIds[$sid] = true;
                }
            }

            // Students with positions are always kept regardless of form submission
            foreach (array_keys($hasPositionIds) as $sid) {
                if (isset($enrolledStudents[$sid])) {
                    $submittedIds[$sid] = true;
                }
            }

            foreach (array_keys($submittedIds) as $sid) {
                if (!isset($enrolledStudents[$sid]) && isset($eligibleStudents[$sid])) {
                    $stay->addStudent($eligibleStudents[$sid]);
                }
            }

            foreach (array_keys($enrolledStudents) as $sid) {
                if (!isset($submittedIds[$sid])) {
                    $stay->removeStudent($enrolledStudents[$sid]);
                }
            }

            $this->em->flush();

            $this->addFlash('success', $this->t('stays.flash.students_saved'));

            return $this->redirectToRoute('app_stays_show', ['id' => $id]);
        }

        return $this->render('stays/manage_students.html.twig', [
            'centre'          => $centre,
            'stay'            => $stay,
            'by_level'        => $byLevel,
            'enrolled_ids'    => $enrolledStudents,
            'position_ids'    => $hasPositionIds,
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
