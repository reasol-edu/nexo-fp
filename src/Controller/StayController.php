<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AcademicYear;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Repository\CompanyRepository;
use App\Repository\GroupRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Security\StayScope;
use App\Security\Voter\StayVoter;
use App\Repository\ProgrammeYearRepository;
use App\Repository\StayRepository;
use App\Repository\TeacherRepository;
use App\Repository\TrainingPositionRepository;
use App\Repository\WorkcenterRepository;
use App\Service\XlsxExporter;
use App\Service\PdfService;
use App\Service\PositionSnapshot;
use App\Service\StayNotifier;
use App\Service\StayRealtimeNotifier;
use App\Service\TenantContext;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/estancias')]
#[IsGranted('ROLE_TEACHER')]
class StayController extends AbstractController
{
    /** Clave de sesión con el último puesto eliminado, para poder deshacerlo, y segundos durante los que se ofrece. */
    private const UNDO_POSITION_SESSION_KEY = 'undo_deleted_position';
    private const UNDO_POSITION_SECONDS = 300;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenant,
        private readonly StayRepository $stays,
        private readonly TrainingPositionRepository $positions,
        private readonly WorkcenterRepository $workcenters,
        private readonly ProgrammeYearRepository $programmeYears,
        private readonly GroupRepository $groups,
        private readonly ProgrammeRepository $programmes,
        private readonly ProfessionalFamilyRepository $families,
        private readonly StayScope $scope,
        private readonly \App\Repository\StudentYearProfileRepository $studentProfiles,
        private readonly TeacherRepository $teachers,
        private readonly TranslatorInterface $translator,
        private readonly PdfService $pdf,
        private readonly XlsxExporter $xlsxExporter,
        private readonly StayNotifier $notifier,
        private readonly StayRealtimeNotifier $realtime,
        private readonly Authorization $mercureAuthorization,
        private readonly ClockInterface $clock,
        #[Target('training_position')]
        private readonly WorkflowInterface $trainingPositionWorkflow,
        private readonly PositionSnapshot $snapshots,
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

        if ($this->tenant->isViewingNonActiveYear($centre)) {
            throw $this->createAccessDeniedException('Write operations are not allowed when viewing a past academic year.');
        }

        $year = $centre->getActiveAcademicYear();
        if ($year === null) {
            $this->addFlash('error', $this->t('stays.flash.no_active_year'));

            return $this->redirectToRoute('app_stays_index');
        }

        $this->denyAccessUnlessGranted(StayVoter::CREATE, $centre);

        /** @var Teacher $teacher */
        $teacher = $this->getUser();
        $uid = $teacher->getId()->toRfc4122();
        $canSeeAll = $teacher->isAdmin()
            || $centre->getAdmins()->exists(fn(int $k, Teacher $a) => $a->getId()->toRfc4122() === $uid);

        // Una estancia puede reunir varias enseñanzas: se ofrecen todas las del curso, pero quien no
        // administra el centro debe poder crear estancias para al menos una de las elegidas.
        $allProgrammes = $this->programmes->findByAcademicYearOrderedByFamilyAndName($year);
        $creatableIds  = [];
        foreach ($canSeeAll ? $allProgrammes : $this->programmes->findCreatableByAcademicYear($teacher, $year) as $p) {
            $creatableIds[$p->getId()->toRfc4122()] = true;
        }

        $byFamily = $this->groupProgrammesByFamily($allProgrammes);

        $errors = [];
        $values = ['name' => '', 'programme_ids' => [], 'start_date' => '', 'end_date' => '', 'calculate_by_teacher' => false];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_stay', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'name'                 => trim($request->request->getString('name')),
                'programme_ids'        => array_values(array_map('strval', $request->request->all('programme_ids'))),
                'start_date'           => trim($request->request->getString('start_date')),
                'end_date'             => trim($request->request->getString('end_date')),
                'calculate_by_teacher' => $request->request->getBoolean('calculate_by_teacher'),
            ];

            if ($values['name'] === '') {
                $errors['name'] = $this->t('stays.error.name_required');
            } elseif ($this->stays->existsByNameAndYear($values['name'], $year)) {
                $errors['name'] = $this->t('stays.error.name_duplicate');
            }

            $selectedProgrammes = $this->findProgrammesOfYear($year, $values['programme_ids']);
            if ($selectedProgrammes === [] || count($selectedProgrammes) !== count(array_unique($values['programme_ids']))) {
                $errors['programme_ids'] = $this->t('stays.error.programme_required');
            } else {
                $anyCreatable = false;
                foreach ($selectedProgrammes as $selected) {
                    $anyCreatable = $anyCreatable || isset($creatableIds[$selected->getId()->toRfc4122()]);
                }
                if (!$anyCreatable) {
                    throw $this->createAccessDeniedException();
                }
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

            if (empty($errors) && $selectedProgrammes !== [] && $startDate !== null && $endDate !== null) {
                $stay = new Stay();
                $stay->setName($values['name'])
                     ->setAcademicYear($year)
                     ->setStartDate($startDate)
                     ->setEndDate($endDate)
                     ->setCalculateByTeacher($values['calculate_by_teacher']);
                foreach ($selectedProgrammes as $selected) {
                    $stay->addProgramme($selected);
                }

                $this->em->persist($stay);
                $this->em->flush();

                $this->addFlash('success', $this->t('stays.flash.created'));

                return $this->redirectToRoute('app_stays_index');
            }
        }

        return $this->render('stays/new.html.twig', [
            'centre'    => $centre,
            'by_family' => $byFamily,
            'creatable_ids' => array_keys($creatableIds),
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
        $year = $this->tenant->getViewYear($centre);

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(StayVoter::DELETE, $stay);

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
        $year = $this->tenant->getViewYear($centre);

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(StayVoter::MANAGE, $stay);

        /** @var Teacher $teacher */
        $teacher = $this->getUser();

        // Enseñanzas que no se pueden quitar porque ya tienen alumnado matriculado o puestos ofertados.
        $lockedProgrammeIds = $this->programmeIdsInUse($stay);

        $errors = [];
        $values = [
            'name'          => $stay->getName(),
            'programme_ids' => array_map(static fn (Programme $p): string => $p->getId()->toRfc4122(), $stay->getProgrammes()->toArray()),
            'start_date'    => $stay->getStartDate()->format('Y-m-d'),
            'end_date'      => $stay->getEndDate()->format('Y-m-d'),
            'calculate_by_teacher' => $stay->isCalculateByTeacher(),
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_stay_' . $id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'name'                 => trim($request->request->getString('name')),
                'programme_ids'        => array_values(array_map('strval', $request->request->all('programme_ids'))),
                'start_date'           => trim($request->request->getString('start_date')),
                'end_date'             => trim($request->request->getString('end_date')),
                'calculate_by_teacher' => $request->request->getBoolean('calculate_by_teacher'),
            ];

            $selectedIds = [];
            $selectedProgrammes = $this->findProgrammesOfYear($year, $values['programme_ids']);
            if ($selectedProgrammes === [] || count($selectedProgrammes) !== count(array_unique($values['programme_ids']))) {
                $errors['programme_ids'] = $this->t('stays.error.programme_required');
            } else {
                $selectedIds = array_map(static fn (Programme $p): string => $p->getId()->toRfc4122(), $selectedProgrammes);
                if (array_diff($lockedProgrammeIds, $selectedIds) !== []) {
                    $errors['programme_ids'] = $this->t('stays.error.programme_in_use');
                } else {
                    // Quien edita debe seguir gestionando al menos una de las enseñanzas resultantes.
                    $keepsAccess = $teacher->isAdmin();
                    foreach ($selectedProgrammes as $selected) {
                        $keepsAccess = $keepsAccess
                            || $this->programmes->isCoordinatorOf($teacher, $selected)
                            || $this->families->isFamilyHeadOfProgramme($teacher, $selected)
                            || $centre->getAdmins()->contains($teacher);
                    }
                    if (!$keepsAccess) {
                        $errors['programme_ids'] = $this->t('stays.error.programme_keep_one');
                    }
                }
            }

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
                     ->setEndDate($endDate)
                     ->setCalculateByTeacher($values['calculate_by_teacher']);
                foreach ($stay->getProgrammes()->toArray() as $current) {
                    if (!in_array($current->getId()->toRfc4122(), $selectedIds, true)) {
                        $stay->removeProgramme($current);
                    }
                }
                foreach ($selectedProgrammes as $selected) {
                    $stay->addProgramme($selected);
                }

                $this->em->flush();
                $this->realtime->publishStayChanged($stay);

                $this->addFlash('success', $this->t('stays.flash.updated'));

                return $this->redirectToRoute('app_stays_show', ['id' => $id]);
            }
        }

        return $this->render('stays/edit.html.twig', [
            'centre'     => $centre,
            'stay'       => $stay,
            'by_family'  => $this->groupProgrammesByFamily($this->programmes->findByAcademicYearOrderedByFamilyAndName($year)),
            'locked_ids' => $lockedProgrammeIds,
            'errors'     => $errors,
            'values'     => $values,
        ]);
    }

    #[Route('/{id}', name: 'app_stays_show')]
    public function show(string $id, Request $request): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $stay = $this->stays->findById($id);
        $year = $this->tenant->getViewYear($centre);

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(StayVoter::VIEW, $stay);

        $topic = $this->realtime->topicForStay($stay);

        $response = $this->render('stays/show.html.twig', [
            'centre'              => $centre,
            'stay'                => $stay,
            'mercure_public_url'  => $this->realtime->publicUrl(),
            'mercure_topic'       => $topic,
            'can_restore_position' => $this->isUndoAvailable($request->getSession()->get(self::UNDO_POSITION_SESSION_KEY), $id)
                && $this->isGranted(StayVoter::ADD_POSITION, $stay),
        ]);

        // Cookie de autorización Mercure: solo concede suscripción al topic de
        // ESTA estancia, y solo a quien acaba de superar StayVoter::VIEW.
        try {
            $response->headers->setCookie(
                $this->mercureAuthorization->createCookie($request, [$topic])
            );
        } catch (\Throwable) {
            // Hub sin factoría de tokens (p. ej. secreto sin configurar): sin
            // tiempo real, pero la página sigue funcionando con normalidad.
        }

        return $response;
    }

    #[Route('/{id}/informe', name: 'app_stays_report')]
    public function report(string $id): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $stay = $this->stays->findById($id);
        $year = $this->tenant->getViewYear($centre);

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(StayVoter::VIEW, $stay);

        $canViewUnassigned = $this->isGranted(StayVoter::VIEW_UNASSIGNED, $stay);
        $allPositions = $this->positions->findByStayOrdered($stay);

        $studentPositionMap  = [];
        $unassignedPositions = [];
        foreach ($allPositions as $tp) {
            if ($tp->getStudent() !== null) {
                $studentPositionMap[$tp->getStudent()->getId()->toRfc4122()] = $tp;
            } elseif ($canViewUnassigned) {
                $unassignedPositions[] = $tp;
            }
        }

        $enrolledStudents = [];
        foreach ($stay->getStudents() as $s) {
            $enrolledStudents[$s->getId()->toRfc4122()] = $s;
        }

        $byGroup          = [];
        $placedStudentIds = [];
        foreach ($this->groups->findByStayWithStudents($stay) as $group) {
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
        $filename           = $reportFilenameBase . '-' . strtolower($slug) . '-' . $this->clock->now()->format('Y-m-d') . '.pdf';

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

    #[Route('/exportar-pendientes', name: 'app_stays_export_pending')]
    public function exportPending(Request $request): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $year = $this->tenant->getViewYear($centre);
        if ($year === null) {
            throw $this->createNotFoundException();
        }

        $search      = mb_substr($request->query->getString('search'), 0, 255);
        $familyId    = $request->query->getString('familyId');
        $programmeId = $request->query->getString('programmeId');
        $showCurrent = $request->query->getBoolean('showCurrent', true);
        $showFuture  = $request->query->getBoolean('showFuture', true);
        $showPast    = $request->query->getBoolean('showPast', true);

        $periods = [];
        if ($showCurrent) {
            $periods[] = 'current';
        }
        if ($showFuture) {
            $periods[] = 'future';
        }
        if ($showPast) {
            $periods[] = 'past';
        }
        if ($periods === []) {
            $periods = ['current', 'future', 'past'];
        }

        $user   = $this->getUser();
        $viewer = $user instanceof Teacher ? $user : null;

        $query = $this->positions->createPendingSignaturesQuery(
            $year, $search, $familyId, $programmeId,
            $periods, 'startDate', 'ASC', $viewer,
        );

        $rows = [];
        foreach ($query->toIterable() as $position) {
            $student    = $position->getStudent();
            $stay       = $position->getStay();
            $workcenter = $position->getWorkcenter();
            $tutor      = $position->getAcademicTutor();

            $groups = implode(', ', array_map(
                static fn ($py): string => $py->getName(),
                $position->getProgrammeYears()->toArray(),
            ));

            $rows[] = [
                $student?->getName()->getLastName() ?? '',
                $student?->getName()->getFirstName() ?? '',
                $stay->getName(),
                $stay->getStartDate()->format('d/m/Y'),
                $groups,
                $workcenter?->getCompany()->getName() ?? '',
                $workcenter?->getName() ?? '',
                $tutor !== null ? $tutor->getName()->getLastName() . ', ' . $tutor->getName()->getFirstName() : '',
            ];
        }

        return $this->xlsxExporter->createResponse(
            'puestos-pendientes-firma-' . $this->clock->now()->format('Y-m-d') . '.xlsx',
            [
                $this->t('stays.export_pending.col.last_name'),
                $this->t('stays.export_pending.col.first_name'),
                $this->t('stays.pending.col.stay'),
                $this->t('stays.pending.col.start_date'),
                $this->t('stays.pending.col.group'),
                $this->t('stays.export.col.company'),
                $this->t('stays.export.col.workcenter'),
                $this->t('stays.pending.col.tutor'),
            ],
            $rows,
        );
    }

    #[Route('/{id}/exportar', name: 'app_stays_export')]
    public function export(string $id): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $stay = $this->stays->findById($id);
        $year = $this->tenant->getViewYear($centre);

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(StayVoter::VIEW, $stay);

        $canViewUnassigned = $this->isGranted(StayVoter::VIEW_UNASSIGNED, $stay);

        /** @var Teacher $actor */
        $actor = $this->getUser();

        // Ficha del curso del alumnado (sin la actitud, que no sale del centro).
        $profiles = $this->studentProfiles->findByStay($stay);
        $yesNo    = fn (?bool $value): string => $value === null ? '' : $this->t($value ? 'stays.export.yes' : 'stays.export.no');

        $rows = [];
        foreach ($this->positions->findByStayOrdered($stay) as $position) {
            $student    = $position->getStudent();
            if ($student === null && !$canViewUnassigned) {
                continue;
            }
            $profile    = $student !== null ? ($profiles[$student->getId()->toRfc4122()] ?? null) : null;
            $tutor     = $position->getAcademicTutor();
            $mentor     = $position->getWorkplaceMentor();
            $workcenter = $position->getWorkcenter();

            $rows[] = [
                $student !== null ? $student->getName()->getLastName() . ', ' . $student->getName()->getFirstName() : '',
                $position->getProgrammeNames(),
                $workcenter?->getCompany()->getName() ?? '',
                $workcenter?->getName() ?? '',
                $workcenter?->getCity() ?? '',
                $tutor !== null ? $tutor->getName()->getLastName() . ', ' . $tutor->getName()->getFirstName() : '',
                $mentor !== null ? $mentor->getName()->getLastName() . ', ' . $mentor->getName()->getFirstName() : '',
                $this->t('stays.state.' . strtolower($position->getState()->value)),
                $position->isSigned() ? $this->t('stays.export.yes') : $this->t('stays.export.no'),
                $position->getDetails(),
                $yesNo($profile?->hasNuss()),
                $profile !== null ? $yesNo($profile->takesFfeoe()) : '',
                $profile !== null ? $yesNo($profile->hasCar()) : '',
                $profile?->bringsCompany() === true ? ($profile->getBroughtCompanyName() ?? '') : '',
                $profile?->getResidenceCity() ?? '',
                $profile?->getPreferredCity() ?? '',
            ];
        }

        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $stay->getName()));

        return $this->xlsxExporter->createResponse(
            'estancia-' . $slug . '-' . $this->clock->now()->format('Y-m-d') . '.xlsx',
            [
                $this->t('stays.show.col.student'),
                $this->t('stays.export.col.programme'),
                $this->t('stays.export.col.company'),
                $this->t('stays.export.col.workcenter'),
                $this->t('stays.export.col.city'),
                $this->t('stays.show.col.tutor'),
                $this->t('stays.show.col.workplace_mentor'),
                $this->t('stays.show.col.state'),
                $this->t('stays.position.field.signed'),
                $this->t('stays.show.col.details'),
                $this->t('stays.export.col.nuss'),
                $this->t('stays.export.col.takes_ffeoe'),
                $this->t('stays.export.col.car'),
                $this->t('stays.export.col.brought_company'),
                $this->t('stays.export.col.residence'),
                $this->t('stays.export.col.preferred_city'),
            ],
            $rows,
        );
    }

    #[Route('/{id}/nuevo-puesto', name: 'app_stays_new_position')]
    public function newPosition(string $id, Request $request): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $stay = $this->stays->findById($id);
        $year = $this->tenant->getViewYear($centre);

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(StayVoter::ADD_POSITION, $stay);

        /** @var Teacher $currentUser */
        $currentUser = $this->getUser();
        $canManage   = $this->isGranted(StayVoter::MANAGE, $stay);

        $allWorkcenters = $canManage
            ? $this->workcenters->findByCentreOrdered($centre)
            : $this->workcenters->findByCentreAndLiaisonOrdered($centre, $currentUser);

        $byCompany = [];
        foreach ($allWorkcenters as $wc) {
            $cid = $wc->getCompany()->getId()->toRfc4122();
            if (!isset($byCompany[$cid])) {
                $byCompany[$cid] = ['company' => $wc->getCompany(), 'workcenters' => []];
            }
            $byCompany[$cid]['workcenters'][] = $wc;
        }

        $programmeYears = $this->programmeYears->findByStayOrderedByName($stay);

        $errors = [];
        $values = ['workcenter_id' => '', 'programme_year_ids' => [], 'priority_programme_year_id' => '', 'priority_until' => '', 'details' => '', 'count' => '1'];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_position_' . $id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'workcenter_id'      => trim($request->request->getString('workcenter_id')),
                'programme_year_ids' => $request->request->all('programme_year_ids'),
                'priority_programme_year_id' => trim($request->request->getString('priority_programme_year_id')),
                'priority_until'     => trim($request->request->getString('priority_until')),
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
                } elseif (!$canManage && !$workcenter->getCompany()->getLiaisons()->contains($currentUser)) {
                    throw $this->createAccessDeniedException();
                }
            }

            $selectedYears = [];
            foreach ($values['programme_year_ids'] as $pyId) {
                $py = $this->programmeYears->findByStayAndId($stay, (string) $pyId);
                if ($py !== null) {
                    $selectedYears[] = $py;
                }
            }
            if ($programmeYears !== [] && $selectedYears === []) {
                $errors['programme_year_ids'] = $this->t('stays.error.programme_year_required');
            }

            [$priorityLevel, $priorityUntil] = $this->resolvePriority(
                $stay, $selectedYears, $values['priority_programme_year_id'], $values['priority_until'], $errors,
            );

            if (empty($errors)) {
                for ($i = 0; $i < $count; $i++) {
                    $position = new TrainingPosition();
                    $position->setStay($stay)
                             ->setWorkcenter($workcenter)
                             ->setPriority($priorityLevel, $priorityUntil)
                             ->setDetails($values['details'] !== '' ? $values['details'] : null);
                    foreach ($selectedYears as $py) {
                        $position->addProgrammeYear($py);
                    }
                    $this->em->persist($position);
                }
                $this->em->flush();
                $this->realtime->publishStayChanged($stay);

                $this->notifier->notifyLiaisonsPositionsCreated($stay, $workcenter->getCompany(), $count, $currentUser);

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

    /**
     * Copia puestos libres de otra estancia del mismo curso: mismo centro de trabajo y observaciones, y
     * los niveles que la estancia de destino también oferta. No se copian estudiante, tutores ni preferencia.
     */
    #[Route('/{id}/copiar-puestos', name: 'app_stays_copy_positions')]
    public function copyPositions(string $id, Request $request): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $stay = $this->stays->findById($id);
        $year = $this->tenant->getViewYear($centre);

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(StayVoter::ADD_POSITION, $stay);

        /** @var Teacher $currentUser */
        $currentUser = $this->getUser();

        // Quien no gestiona la estancia solo puede ofertar puestos en las empresas de las que es enlace.
        $allowedWorkcenterIds = null;
        if (!$this->isGranted(StayVoter::MANAGE, $stay)) {
            $allowedWorkcenterIds = [];
            foreach ($this->workcenters->findByCentreAndLiaisonOrdered($centre, $currentUser) as $wc) {
                $allowedWorkcenterIds[$wc->getId()->toRfc4122()] = true;
            }
        }

        $sources = array_values(array_filter(
            $this->stays->findOthersByYear($year, $stay),
            fn (Stay $other): bool => $this->isGranted(StayVoter::VIEW, $other),
        ));

        $sourceId = $request->isMethod('POST') ? $request->request->getString('source') : $request->query->getString('origen');
        $source   = null;
        foreach ($sources as $candidate) {
            if ($candidate->getId()->toRfc4122() === $sourceId) {
                $source = $candidate;
                break;
            }
        }

        $targetYearIds = [];
        foreach ($this->programmeYears->findByStayOrderedByName($stay) as $py) {
            $targetYearIds[$py->getId()->toRfc4122()] = $py;
        }

        // Puestos del origen que se pueden copiar y los niveles con los que quedarían en esta estancia.
        $rows = [];
        if ($source !== null) {
            foreach ($this->positions->findByStayOrdered($source) as $position) {
                $workcenter = $position->getWorkcenter();
                if ($workcenter === null
                    || ($allowedWorkcenterIds !== null && !isset($allowedWorkcenterIds[$workcenter->getId()->toRfc4122()]))
                ) {
                    continue;
                }

                $years = [];
                foreach ($position->getProgrammeYears() as $py) {
                    if (isset($targetYearIds[$py->getId()->toRfc4122()])) {
                        $years[] = $py;
                    }
                }
                // Sin ningún nivel que esta estancia oferte, el puesto no tendría a quién ir dirigido.
                if ($years === []) {
                    continue;
                }

                $rows[$position->getId()->toRfc4122()] = ['position' => $position, 'years' => $years];
            }
        }

        if ($request->isMethod('POST') && $source !== null) {
            if (!$this->isCsrfTokenValid('copy_positions_' . $id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $count = 0;
            foreach (array_unique(array_map('strval', $request->request->all('positions'))) as $positionId) {
                if (!isset($rows[$positionId])) {
                    continue;
                }
                $original = $rows[$positionId]['position'];
                $copy     = (new TrainingPosition())
                    ->setStay($stay)
                    ->setWorkcenter($original->getWorkcenter())
                    ->setDetails($original->getDetails());
                foreach ($rows[$positionId]['years'] as $py) {
                    $copy->addProgrammeYear($py);
                }
                $this->em->persist($copy);
                ++$count;
            }

            if ($count === 0) {
                $this->addFlash('error', $this->t('stays.copy.none_selected'));

                return $this->redirectToRoute('app_stays_copy_positions', ['id' => $id, 'origen' => $sourceId]);
            }

            $this->em->flush();
            $this->realtime->publishStayChanged($stay);
            $this->addFlash('success', $this->translator->trans('stays.flash.positions_copied', ['%count%' => $count, '%stay%' => $source->getName()], 'stays'));

            return $this->redirectToRoute('app_stays_show', ['id' => $id]);
        }

        return $this->render('stays/copy_positions.html.twig', [
            'centre'  => $centre,
            'stay'    => $stay,
            'sources' => $sources,
            'source'  => $source,
            'rows'    => array_values($rows),
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
        $year = $this->tenant->getViewYear($centre);

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(StayVoter::MANAGE_POSITION, $position);

        if (!$this->isCsrfTokenValid('delete_position_' . $positionId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var Teacher $actor */
        $actor = $this->getUser();
        $wasFree = $position->getStudent() === null;

        // Se guarda en la sesión lo necesario para «Deshacer» (la estancia lo ofrece en la página siguiente).
        $request->getSession()->set(self::UNDO_POSITION_SESSION_KEY, [
            'stay'     => $id,
            'at'       => $this->clock->now()->getTimestamp(),
            'snapshot' => $this->snapshots->capture($position),
        ]);

        $this->em->remove($position);
        $this->em->flush();
        $this->realtime->publishStayChanged($stay);

        // Un puesto libre puede estar ofertado a otras enseñanzas: se avisa a sus coordinaciones.
        if ($wasFree) {
            $actorProgrammes = array_values(array_filter(
                $stay->getProgrammes()->toArray(),
                fn (Programme $p): bool => $this->scope->canManageProgramme($actor, $stay, $p),
            ));
            $this->notifier->notifySharedPositionRemoved($position, $actor, $actorProgrammes);
        }

        $this->addFlash('success', $this->t('stays.flash.position_deleted'));

        return $this->redirectToRoute('app_stays_show', ['id' => $id]);
    }

    /** ¿Hay una eliminación de puesto de esta estancia reciente que se pueda deshacer? */
    private function isUndoAvailable(mixed $undo, string $stayId): bool
    {
        return \is_array($undo)
            && ($undo['stay'] ?? null) === $stayId
            && \is_array($undo['snapshot'] ?? null)
            && \is_int($undo['at'] ?? null)
            && $this->clock->now()->getTimestamp() - $undo['at'] <= self::UNDO_POSITION_SECONDS;
    }

    /** Deshace la última eliminación de un puesto, volviendo a crearlo con los datos guardados en la sesión. */
    #[Route('/{id}/puesto/restaurar', name: 'app_stays_restore_position', methods: ['POST'])]
    public function restorePosition(string $id, Request $request): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $stay = $this->stays->findById($id);
        $year = $this->tenant->getViewYear($centre);

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(StayVoter::ADD_POSITION, $stay);

        if (!$this->isCsrfTokenValid('restore_position_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $session = $request->getSession();
        $undo    = $session->get(self::UNDO_POSITION_SESSION_KEY);
        $session->remove(self::UNDO_POSITION_SESSION_KEY);
        if (!$this->isUndoAvailable($undo, $id)) {
            $this->addFlash('error', $this->t('stays.flash.position_restore_unavailable'));

            return $this->redirectToRoute('app_stays_show', ['id' => $id]);
        }

        /** @var array{snapshot: array<string, mixed>} $undo */
        $position = $this->snapshots->restore($stay, $undo['snapshot']);
        $this->em->persist($position);
        $this->em->flush();
        $this->realtime->publishStayChanged($stay);

        $this->addFlash('success', $this->t('stays.flash.position_restored'));

        return $this->redirectToRoute('app_stays_show', ['id' => $id]);
    }

    #[Route('/{id}/puesto/{positionId}/duplicar', name: 'app_stays_duplicate_position', methods: ['POST'])]
    public function duplicatePosition(string $id, string $positionId, Request $request): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $stay = $this->stays->findById($id);
        $year = $this->tenant->getViewYear($centre);

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $original = $this->positions->findByIdAndStay($positionId, $stay);
        if ($original === null) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(StayVoter::MANAGE_POSITION, $original);

        if (!$this->isCsrfTokenValid('duplicate_position_' . $positionId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $copies = max(1, min(20, $request->request->getInt('copies', 1)));

        $created = [];
        for ($i = 0; $i < $copies; $i++) {
            $copy = new TrainingPosition();
            $copy->setStay($stay)
                 ->setWorkcenter($original->getWorkcenter())
                 ->setPriority($original->getPriorityProgrammeYear(), $original->getPriorityUntil())
                 ->setDetails($original->getDetails());
            foreach ($original->getProgrammeYears() as $py) {
                $copy->addProgrammeYear($py);
            }
            $this->em->persist($copy);
            $created[] = $copy;
        }

        $this->em->flush();
        $this->realtime->publishStayChanged($stay);

        if ($copies > 1) {
            $this->addFlash('success', $this->translator->trans('stays.flash.positions_duplicated', ['%count%' => $copies], 'stays'));

            return $this->redirectToRoute('app_stays_show', ['id' => $id]);
        }

        $this->addFlash('success', $this->t('stays.flash.position_duplicated'));

        return $this->redirectToRoute('app_stays_edit_position', ['id' => $id, 'positionId' => $created[0]->getId()->toRfc4122()]);
    }

    #[Route('/{id}/puesto/{positionId}/editar', name: 'app_stays_edit_position')]
    public function editPosition(string $id, string $positionId, Request $request): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $stay = $this->stays->findById($id);
        $year = $this->tenant->getViewYear($centre);

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(StayVoter::MANAGE_POSITION, $position);

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

        $programmeYears = $this->programmeYears->findByStayOrderedByName($stay);

        // Only teachers who teach in the programme's groups
        $teachers = $this->teachers->findByStayProgrammesOrderedByName($stay);
        // Ensure the current tutor is in the list even if they no longer teach in the programme
        $currentTutor = $position->getAcademicTutor();
        if ($currentTutor !== null) {
            $teacherIds = array_map(fn ($t) => $t->getId()->toRfc4122(), $teachers);
            if (!\in_array($currentTutor->getId()->toRfc4122(), $teacherIds, true)) {
                array_unshift($teachers, $currentTutor);
            }
        }

        // Los grupos se cargan antes que el alumnado: así este llega con sus grupos inicializados y
        // deducir sus enseñanzas (alcance de la coordinación) no lanza una consulta por alumno.
        $stayGroups = $this->groups->findByStayWithStudents($stay);

        // Enrolled students in the stay
        $enrolledStudents = [];
        foreach ($stay->getStudents() as $s) {
            $enrolledStudents[$s->getId()->toRfc4122()] = $s;
        }
        uasort($enrolledStudents, fn ($a, $b) =>
            $a->getName()->getLastName() <=> $b->getName()->getLastName()
            ?: $a->getName()->getFirstName() <=> $b->getName()->getFirstName()
        );

        // Quien edita solo puede asignar a este puesto a estudiantes que gestiona (el actual se conserva).
        /** @var Teacher $actor */
        $actor = $this->getUser();
        $assignableStudents = array_filter(
            $enrolledStudents,
            fn ($s) => $s->getId()->toRfc4122() === ($position->getStudent()?->getId()->toRfc4122() ?? '')
                || $this->scope->canManageStudent($actor, $stay, $s)
        );

        // Student → group map (group within the programme)
        $studentGroupMap = [];
        foreach ($stayGroups as $group) {
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
        $currentWorkcenterId = $position->getWorkcenter()->getId()->toRfc4122();
        $currentStudentId = $position->getStudent()?->getId()->toRfc4122() ?? '';
        $currentTutorId = $position->getAcademicTutor()?->getId()->toRfc4122() ?? '';
        $currentMentorId = $position->getWorkplaceMentor()?->getId()->toRfc4122() ?? '';
        $isAssignmentLocked = $position->getState() !== TrainingPositionState::DRAFT;

        $errors = [];
        $values = [
            'workcenter_id'       => $position->getWorkcenter()?->getId()->toRfc4122() ?? '',
            'programme_year_ids'  => $currentPyIds,
            'priority_programme_year_id' => $position->getPriorityProgrammeYear()?->getId()->toRfc4122() ?? '',
            'priority_until'      => $position->getPriorityUntil()?->format('Y-m-d') ?? '',
            'details'             => $position->getDetails() ?? '',
            'student_id'          => $currentStudentId,
            'academic_tutor_id'   => $currentTutorId,
            'workplace_mentor_id' => $currentMentorId,
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
                'priority_programme_year_id' => trim($request->request->getString('priority_programme_year_id')),
                'priority_until'      => trim($request->request->getString('priority_until')),
                'details'             => trim($request->request->getString('details')),
                'student_id'          => trim($request->request->getString('student_id')),
                'academic_tutor_id'   => trim($request->request->getString('academic_tutor_id')),
                'workplace_mentor_id' => trim($request->request->getString('workplace_mentor_id')),
                'state'               => trim($request->request->getString('state')),
                'signed'              => $request->request->has('signed'),
            ];

            if ($isAssignmentLocked) {
                $values['workcenter_id'] = $currentWorkcenterId;
                $values['student_id'] = $currentStudentId;
                $values['academic_tutor_id'] = $currentTutorId;
                $values['workplace_mentor_id'] = $currentMentorId;
                $values['programme_year_ids'] = $currentPyIds;
                $values['priority_programme_year_id'] = $position->getPriorityProgrammeYear()?->getId()->toRfc4122() ?? '';
                $values['priority_until'] = $position->getPriorityUntil()?->format('Y-m-d') ?? '';
            }

            // Bloqueo optimista: si otra persona guardó mientras se editaba, la
            // versión enviada no coincide con la cargada. En vez de descartar lo
            // tecleado, conservamos los valores enviados, recargamos la versión
            // vigente y avisamos en línea para que se revise antes de reintentar.
            $submittedVersion = $request->request->getInt('version', $position->getVersion());
            try {
                $this->em->lock($position, LockMode::OPTIMISTIC, $submittedVersion);
            } catch (OptimisticLockException) {
                $this->em->refresh($position);

                return $this->render('stays/edit_position.html.twig', [
                    'centre'              => $centre,
                    'stay'                => $stay,
                    'position'            => $position,
                    'is_assignment_locked'=> $isAssignmentLocked,
                    'by_company'          => $byCompany,
                    'workers_by_company'  => $workersByCompany,
                    'programme_years'     => $programmeYears,
                    'teachers'            => $teachers,
                    'enrolled_students'   => $assignableStudents,
                    'student_group_map'   => $studentGroupMap,
                    'other_assigned_ids'  => $otherAssignedIds,
                    'errors'              => [],
                    'values'              => $values,
                    'conflict'            => true,
                ]);
            }

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
                $py = $this->programmeYears->findByStayAndId($stay, (string) $pyId);
                if ($py !== null) {
                    $selectedYears[] = $py;
                }
            }
            if ($programmeYears !== [] && $selectedYears === []) {
                $errors['programme_year_ids'] = $this->t('stays.error.programme_year_required');
            }

            [$priorityLevel, $priorityUntil] = $isAssignmentLocked
                ? [$position->getPriorityProgrammeYear(), $position->getPriorityUntil()]
                : $this->resolvePriority($stay, $selectedYears, $values['priority_programme_year_id'], $values['priority_until'], $errors);

            // Validate student (optional)
            $student = null;
            if ($values['student_id'] !== '') {
                if (isset($enrolledStudents[$values['student_id']])) {
                    if (isset($otherAssignedIds[$values['student_id']])) {
                        $errors['student_id'] = $this->t('stays.error.student_already_assigned');
                    } elseif ($values['student_id'] !== $currentStudentId
                        && !isset($assignableStudents[$values['student_id']])
                    ) {
                        $errors['student_id'] = $this->t('stays.error.student_not_yours');
                    } elseif ($values['student_id'] !== $currentStudentId
                        && !$position->acceptsStudent($enrolledStudents[$values['student_id']], $selectedYears)
                    ) {
                        $errors['student_id'] = $this->t('stays.error.student_incompatible');
                    } elseif ($values['student_id'] !== $currentStudentId
                        && $priorityLevel !== null && $priorityUntil !== null
                        && $position->isReservedAgainst($enrolledStudents[$values['student_id']], $this->clock->now(), $priorityLevel, $priorityUntil)
                    ) {
                        $errors['student_id'] = $this->t('stays.error.student_reserved');
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

                // Apply assignment changes first so the state-machine guards
                // evaluate the new tutors before deciding the transition.
                $position->setWorkcenter($workcenter)
                         ->setPriority($priorityLevel, $priorityUntil)
                         ->setDetails($values['details'] !== '' ? $values['details'] : null)
                         ->setStudent($student)
                         ->setAcademicTutor($academicTutor)
                         ->setWorkplaceMentor($workplaceMentor);

                $transition = 'to_' . strtolower($state->value);
                if ($state === $position->getState()) {
                    $position->setSigned($values['signed']);
                } elseif ($this->trainingPositionWorkflow->can($position, $transition)) {
                    $this->trainingPositionWorkflow->apply($position, $transition);
                    $position->setSigned($values['signed']);
                } else {
                    $errors['state'] = $this->t('stays.error.state_requires_tutors');
                }
            }

            if (empty($errors)) {
                $this->em->flush();
                $this->realtime->publishStayChanged($stay);

                if ($academicTutor !== null && $academicTutor->getId()->toRfc4122() !== $currentTutorId) {
                    $this->notifier->notifyTutorAssigned($position);
                }

                $this->addFlash('success', $this->t('stays.flash.position_updated'));

                return $this->redirectToRoute('app_stays_show', ['id' => $id]);
            }
        }

        return $this->render('stays/edit_position.html.twig', [
            'centre'              => $centre,
            'stay'                => $stay,
            'position'            => $position,
            'is_assignment_locked'=> $isAssignmentLocked,
            'by_company'          => $byCompany,
            'workers_by_company'  => $workersByCompany,
            'programme_years'     => $programmeYears,
            'teachers'            => $teachers,
            'enrolled_students'   => $assignableStudents,
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
        $year = $this->tenant->getViewYear($centre);

        if ($stay === null || $year === null
            || $stay->getAcademicYear()->getId()->toRfc4122() !== $year->getId()->toRfc4122()
        ) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(StayVoter::MANAGE, $stay);

        /** @var Teacher $actor */
        $actor = $this->getUser();

        // Grupos con su alumnado de todas las enseñanzas de la estancia
        $groupList = $this->groups->findByStayWithStudents($stay);

        // Se organiza por nivel (cada nivel pertenece a una enseñanza) para la plantilla
        $byLevel = [];
        $eligibleStudents = [];
        $manageableIds = [];
        foreach ($groupList as $group) {
            $pyId = $group->getProgrammeYear()->getId()->toRfc4122();
            if (!isset($byLevel[$pyId])) {
                $byLevel[$pyId] = ['level' => $group->getProgrammeYear(), 'groups' => []];
            }
            $byLevel[$pyId]['groups'][] = $group;
            foreach ($group->getStudents() as $student) {
                $sid = $student->getId()->toRfc4122();
                $eligibleStudents[$sid] = $student;
                $manageableIds[$sid] ??= $this->scope->canManageStudent($actor, $stay, $student);
            }
        }
        $manageableIds = array_keys(array_filter($manageableIds));

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

            // Solo se tocan los estudiantes que gestiona quien edita: el alumnado de las demás
            // enseñanzas (de otras coordinaciones) ni se matricula ni se quita desde aquí.
            $manageable = array_flip($manageableIds);

            $submittedIds = [];
            foreach ($request->request->all('student_ids') as $sid) {
                $sid = (string) $sid;
                if (isset($eligibleStudents[$sid]) && isset($manageable[$sid])) {
                    $submittedIds[$sid] = true;
                }
            }

            foreach (array_keys($submittedIds) as $sid) {
                if (!isset($enrolledStudents[$sid])) {
                    $stay->addStudent($eligibleStudents[$sid]);
                }
            }

            foreach ($enrolledStudents as $sid => $enrolled) {
                $mine = isset($manageable[$sid]) || !isset($eligibleStudents[$sid]) && $this->scope->canManageStudent($actor, $stay, $enrolled);
                if ($mine && !isset($submittedIds[$sid]) && !isset($hasPositionIds[$sid])) {
                    $stay->removeStudent($enrolled);
                }
            }

            $this->em->flush();
            $this->realtime->publishStayChanged($stay);

            $this->addFlash('success', $this->t('stays.flash.students_saved'));

            return $this->redirectToRoute('app_stays_show', ['id' => $id]);
        }

        return $this->render('stays/manage_students.html.twig', [
            'centre'          => $centre,
            'stay'            => $stay,
            'by_level'        => $byLevel,
            'enrolled_ids'    => $enrolledStudents,
            'manageable_ids'  => $manageableIds,
            'position_ids'    => $hasPositionIds,
        ]);
    }

    /**
     * @param iterable<Programme> $programmes
     * @return array<string, array{family: ProfessionalFamily, programmes: list<Programme>}>
     */
    private function groupProgrammesByFamily(iterable $programmes): array
    {
        $byFamily = [];
        foreach ($programmes as $p) {
            $fid = $p->getProfessionalFamily()->getId()->toRfc4122();
            $byFamily[$fid] ??= ['family' => $p->getProfessionalFamily(), 'programmes' => []];
            $byFamily[$fid]['programmes'][] = $p;
        }

        return $byFamily;
    }

    /**
     * Enseñanzas del curso a partir de ids recibidos del formulario (ignora los desconocidos).
     *
     * @param list<string> $ids
     * @return list<Programme>
     */
    private function findProgrammesOfYear(AcademicYear $year, array $ids): array
    {
        $found = [];
        foreach (array_unique($ids) as $id) {
            $programme = $this->programmes->findByAcademicYearAndId($year, $id);
            if ($programme !== null) {
                $found[] = $programme;
            }
        }

        return $found;
    }

    /**
     * Ids de las enseñanzas de la estancia que ya tienen alumnado matriculado o puestos
     * ofertados a alguno de sus niveles; no se pueden quitar de la estancia.
     *
     * @return list<string>
     */
    private function programmeIdsInUse(Stay $stay): array
    {
        $ids = [];
        foreach ($stay->getStudents() as $student) {
            foreach ($stay->getProgrammesOfStudent($student) as $programme) {
                $ids[$programme->getId()->toRfc4122()] = true;
            }
        }
        foreach ($this->positions->findByStayOrdered($stay) as $position) {
            foreach ($position->getProgrammeYears() as $programmeYear) {
                $ids[$programmeYear->getProgramme()->getId()->toRfc4122()] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Interpreta la preferencia de nivel de un puesto (nivel + fecha límite). Sin ninguno de los dos
     * campos no hay preferencia. El nivel debe ser uno de los elegidos para el puesto (o de la estancia
     * si el puesto no tiene niveles).
     *
     * @param list<\App\Entity\ProgrammeYear> $selectedYears
     * @param array<string, string>              $errors
     * @return array{0: ?\App\Entity\ProgrammeYear, 1: ?\DateTimeImmutable}
     */
    private function resolvePriority(Stay $stay, array $selectedYears, string $programmeYearId, string $until, array &$errors): array
    {
        if ($programmeYearId === '' && $until === '') {
            return [null, null];
        }

        $candidates = $selectedYears !== [] ? $selectedYears : $stay->getProgrammeYears()->toArray();

        $level = null;
        foreach ($candidates as $candidate) {
            if ($candidate->getId()->toRfc4122() === $programmeYearId) {
                $level = $candidate;
                break;
            }
        }
        if ($level === null) {
            $errors['priority_programme_year_id'] = $this->t('stays.error.priority_programme_invalid');
        }

        $date = $until !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $until) : false;
        if ($date === false) {
            $errors['priority_until'] = $this->t('stays.error.priority_date_required');
            $date = null;
        }

        return [$level, $date];
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'stays');
    }
}
