<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Repository\EducationalCentreRepository;
use App\Repository\GroupRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\ProgrammeYearRepository;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/centros/{centreId}/familias')]
#[IsGranted('ROLE_ADMIN')]
class ProfessionalFamilyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EducationalCentreRepository $centres,
        private readonly ProfessionalFamilyRepository $families,
        private readonly ProgrammeRepository $programmes,
        private readonly ProgrammeYearRepository $levels,
        private readonly GroupRepository $groups,
        private readonly TeacherRepository $teachers,
        private readonly TranslatorInterface $translator,
    ) {}

    // ── Familias ──────────────────────────────────────────────────────────────

    #[Route('', name: 'app_admin_families_index')]
    public function index(string $centreId): Response
    {
        $centre = $this->requireCentre($centreId);

        return $this->render('admin/family/index.html.twig', ['centre' => $centre]);
    }

    #[Route('/nueva', name: 'app_admin_families_new')]
    public function new(string $centreId, Request $request): Response
    {
        $centre = $this->requireCentreWithActiveYear($centreId);
        $errors = [];
        $values = ['name' => ''];
        $selectedHead = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_family', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = ['name' => trim($request->request->getString('name'))];

            if ($values['name'] === '') {
                $errors['name'] = $this->t('family.error.name_required');
            }

            $headId = trim($request->request->getString('head'));
            $head   = $headId !== '' ? $this->teachers->findById($headId) : null;

            if (empty($errors)) {
                $family = (new ProfessionalFamily())
                    ->setName($values['name'])
                    ->setAcademicYear($centre->getActiveAcademicYear())
                    ->setHead($head);

                $this->em->persist($family);
                $this->em->flush();

                $this->addFlash('success', $this->t('family.flash.created'));

                return $this->redirectToRoute('app_admin_families_edit', [
                    'centreId' => $centreId,
                    'familyId' => $family->getId()->toRfc4122(),
                ]);
            }

            $selectedHead = $head;
        }

        return $this->render('admin/family/new.html.twig', [
            'centre'       => $centre,
            'errors'       => $errors,
            'values'       => $values,
            'selectedHead' => $selectedHead,
        ]);
    }

    #[Route('/{familyId}', name: 'app_admin_families_edit')]
    public function edit(string $centreId, string $familyId, Request $request): Response
    {
        $centre = $this->requireCentreWithActiveYear($centreId);
        $family = $this->requireFamily($centre->getActiveAcademicYear(), $familyId);

        $errors = [];
        $values = ['name' => $family->getName()];
        $selectedHead = $family->getHead();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_family_' . $familyId, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = ['name' => trim($request->request->getString('name'))];

            if ($values['name'] === '') {
                $errors['name'] = $this->t('family.error.name_required');
            }

            $headId = trim($request->request->getString('head'));
            $head   = $headId !== '' ? $this->teachers->findById($headId) : null;

            if (empty($errors)) {
                $family->setName($values['name'])->setHead($head);
                $this->em->flush();

                $this->addFlash('success', $this->t('family.flash.saved'));

                return $this->redirectToRoute('app_admin_families_edit', [
                    'centreId' => $centreId,
                    'familyId' => $familyId,
                ]);
            }

            $selectedHead = $head;
        }

        return $this->render('admin/family/edit.html.twig', [
            'centre'       => $centre,
            'family'       => $family,
            'programmes'   => $this->programmes->findByFamilyOrderedByName($family),
            'errors'       => $errors,
            'values'       => $values,
            'selectedHead' => $selectedHead,
        ]);
    }

    #[Route('/{familyId}/eliminar', name: 'app_admin_families_delete', methods: ['POST'])]
    public function delete(string $centreId, string $familyId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_family_' . $familyId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $centre = $this->requireCentreWithActiveYear($centreId);
        $family = $this->requireFamily($centre->getActiveAcademicYear(), $familyId);

        try {
            $this->em->remove($family);
            $this->em->flush();
            $this->addFlash('success', $this->t('family.flash.deleted'));
        } catch (\Exception) {
            $this->addFlash('error', $this->t('family.flash.delete_error'));
        }

        return $this->redirectToRoute('app_admin_families_index', ['centreId' => $centreId]);
    }

    // ── Enseñanzas ────────────────────────────────────────────────────────────

    #[Route('/{familyId}/enseñanzas', name: 'app_admin_programmes_add', methods: ['POST'])]
    public function addProgramme(string $centreId, string $familyId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('add_programme_' . $familyId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $centre = $this->requireCentreWithActiveYear($centreId);
        $family = $this->requireFamily($centre->getActiveAcademicYear(), $familyId);

        $name = trim($request->request->getString('name'));

        if ($name === '') {
            $this->addFlash('error', $this->t('programmes.flash.name_required'));
        } else {
            $programme = (new Programme())
                ->setName($name)
                ->setProfessionalFamily($family)
                ->setAcademicYear($centre->getActiveAcademicYear());

            $this->em->persist($programme);
            $this->em->flush();

            $this->addFlash('success', $this->t('programme.flash.added'));
        }

        return $this->redirectToRoute('app_admin_families_edit', [
            'centreId' => $centreId,
            'familyId' => $familyId,
        ]);
    }

    #[Route('/{familyId}/enseñanzas/{programmeId}', name: 'app_admin_programmes_edit')]
    public function editProgramme(
        string $centreId,
        string $familyId,
        string $programmeId,
        Request $request,
    ): Response {
        $centre    = $this->requireCentreWithActiveYear($centreId);
        $family    = $this->requireFamily($centre->getActiveAcademicYear(), $familyId);
        $programme = $this->requireProgramme($family, $programmeId);

        $errors           = [];
        $values           = ['name' => $programme->getName(), 'details' => $programme->getDetails() ?? ''];
        $selectedCoordinators = $programme->getCoordinators()->toArray();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_programme_' . $programmeId, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'name'    => trim($request->request->getString('name')),
                'details' => trim($request->request->getString('details')),
            ];

            if ($values['name'] === '') {
                $errors['name'] = $this->t('programme.error.name_required');
            }

            $coordinatorIds = array_values(array_filter(
                array_map(
                    static fn(mixed $v): string => \is_string($v) ? $v : '',
                    $request->request->all('coordinators'),
                ),
                static fn(string $v): bool => $v !== '',
            ));

            if (!empty($errors)) {
                $selectedCoordinators = array_filter(
                    array_map(fn(string $id) => $this->teachers->findById($id), $coordinatorIds),
                );
            } else {
                $programme->setName($values['name'])
                    ->setDetails($values['details'] !== '' ? $values['details'] : null);

                foreach ($programme->getCoordinators()->toArray() as $c) {
                    $programme->removeCoordinator($c);
                }
                foreach ($coordinatorIds as $id) {
                    $teacher = $this->teachers->findById($id);
                    if ($teacher !== null) {
                        $programme->addCoordinator($teacher);
                    }
                }

                $this->em->flush();
                $this->addFlash('success', $this->t('programme.flash.saved'));

                return $this->redirectToRoute('app_admin_programmes_edit', [
                    'centreId'    => $centreId,
                    'familyId'    => $familyId,
                    'programmeId' => $programmeId,
                ]);
            }
        }

        return $this->render('admin/programme/edit.html.twig', [
            'centre'               => $centre,
            'family'               => $family,
            'programme'            => $programme,
            'levels'               => $this->levels->findByProgrammeOrderedByName($programme),
            'errors'               => $errors,
            'values'               => $values,
            'selectedCoordinators' => $selectedCoordinators,
        ]);
    }

    #[Route('/{familyId}/enseñanzas/{programmeId}/eliminar', name: 'app_admin_programmes_delete', methods: ['POST'])]
    public function deleteProgramme(
        string $centreId,
        string $familyId,
        string $programmeId,
        Request $request,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_programme_' . $programmeId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $centre    = $this->requireCentreWithActiveYear($centreId);
        $family    = $this->requireFamily($centre->getActiveAcademicYear(), $familyId);
        $programme = $this->requireProgramme($family, $programmeId);

        try {
            $this->em->remove($programme);
            $this->em->flush();
            $this->addFlash('success', $this->t('programme.flash.deleted'));
        } catch (\Exception) {
            $this->addFlash('error', $this->t('programme.flash.delete_error'));
        }

        return $this->redirectToRoute('app_admin_families_edit', [
            'centreId' => $centreId,
            'familyId' => $familyId,
        ]);
    }

    // ── Niveles ───────────────────────────────────────────────────────────────

    #[Route('/{familyId}/enseñanzas/{programmeId}/niveles', name: 'app_admin_levels_add', methods: ['POST'])]
    public function addLevel(
        string $centreId,
        string $familyId,
        string $programmeId,
        Request $request,
    ): Response {
        if (!$this->isCsrfTokenValid('add_level_' . $programmeId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $centre    = $this->requireCentreWithActiveYear($centreId);
        $family    = $this->requireFamily($centre->getActiveAcademicYear(), $familyId);
        $programme = $this->requireProgramme($family, $programmeId);

        $name = trim($request->request->getString('name'));

        if ($name === '') {
            $this->addFlash('error', $this->t('levels.flash.name_required'));
        } else {
            $level = (new ProgrammeYear())
                ->setName($name)
                ->setProgramme($programme);

            $this->em->persist($level);
            $this->em->flush();

            $this->addFlash('success', $this->t('level.flash.added'));
        }

        return $this->redirectToRoute('app_admin_programmes_edit', [
            'centreId'    => $centreId,
            'familyId'    => $familyId,
            'programmeId' => $programmeId,
        ]);
    }

    #[Route('/{familyId}/enseñanzas/{programmeId}/niveles/{levelId}', name: 'app_admin_levels_edit')]
    public function editLevel(
        string $centreId,
        string $familyId,
        string $programmeId,
        string $levelId,
        Request $request,
    ): Response {
        $centre    = $this->requireCentreWithActiveYear($centreId);
        $family    = $this->requireFamily($centre->getActiveAcademicYear(), $familyId);
        $programme = $this->requireProgramme($family, $programmeId);
        $level     = $this->requireLevel($programme, $levelId);

        $errors = [];
        $values = ['name' => $level->getName(), 'details' => $level->getDetails() ?? ''];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_level_' . $levelId, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'name'    => trim($request->request->getString('name')),
                'details' => trim($request->request->getString('details')),
            ];

            if ($values['name'] === '') {
                $errors['name'] = $this->t('level.error.name_required');
            }

            if (empty($errors)) {
                $level->setName($values['name'])
                    ->setDetails($values['details'] !== '' ? $values['details'] : null);

                $this->em->flush();
                $this->addFlash('success', $this->t('level.flash.saved'));

                return $this->redirectToRoute('app_admin_levels_edit', [
                    'centreId'    => $centreId,
                    'familyId'    => $familyId,
                    'programmeId' => $programmeId,
                    'levelId'     => $levelId,
                ]);
            }
        }

        return $this->render('admin/level/edit.html.twig', [
            'centre'    => $centre,
            'family'    => $family,
            'programme' => $programme,
            'level'     => $level,
            'groups'    => $this->groups->findByLevelOrderedByName($level),
            'errors'    => $errors,
            'values'    => $values,
        ]);
    }

    #[Route('/{familyId}/enseñanzas/{programmeId}/niveles/{levelId}/eliminar', name: 'app_admin_levels_delete', methods: ['POST'])]
    public function deleteLevel(
        string $centreId,
        string $familyId,
        string $programmeId,
        string $levelId,
        Request $request,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_level_' . $levelId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $centre    = $this->requireCentreWithActiveYear($centreId);
        $family    = $this->requireFamily($centre->getActiveAcademicYear(), $familyId);
        $programme = $this->requireProgramme($family, $programmeId);
        $level     = $this->requireLevel($programme, $levelId);

        try {
            $this->em->remove($level);
            $this->em->flush();
            $this->addFlash('success', $this->t('level.flash.deleted'));
        } catch (\Exception) {
            $this->addFlash('error', $this->t('level.flash.delete_error'));
        }

        return $this->redirectToRoute('app_admin_programmes_edit', [
            'centreId'    => $centreId,
            'familyId'    => $familyId,
            'programmeId' => $programmeId,
        ]);
    }

    // ── Grupos ────────────────────────────────────────────────────────────────

    #[Route('/{familyId}/enseñanzas/{programmeId}/niveles/{levelId}/grupos', name: 'app_admin_groups_add', methods: ['POST'])]
    public function addGroup(
        string $centreId,
        string $familyId,
        string $programmeId,
        string $levelId,
        Request $request,
    ): Response {
        if (!$this->isCsrfTokenValid('add_group_' . $levelId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $centre    = $this->requireCentreWithActiveYear($centreId);
        $family    = $this->requireFamily($centre->getActiveAcademicYear(), $familyId);
        $programme = $this->requireProgramme($family, $programmeId);
        $level     = $this->requireLevel($programme, $levelId);

        $name = trim($request->request->getString('name'));

        if ($name === '') {
            $this->addFlash('error', $this->t('groups.flash.name_required'));
        } else {
            $group = (new Group())
                ->setName($name)
                ->setProgrammeYear($level);

            $this->em->persist($group);
            $this->em->flush();

            $this->addFlash('success', $this->t('group.flash.added'));
        }

        return $this->redirectToRoute('app_admin_levels_edit', [
            'centreId'    => $centreId,
            'familyId'    => $familyId,
            'programmeId' => $programmeId,
            'levelId'     => $levelId,
        ]);
    }

    #[Route('/{familyId}/enseñanzas/{programmeId}/niveles/{levelId}/grupos/{groupId}', name: 'app_admin_groups_edit')]
    public function editGroup(
        string $centreId,
        string $familyId,
        string $programmeId,
        string $levelId,
        string $groupId,
        Request $request,
    ): Response {
        $centre    = $this->requireCentreWithActiveYear($centreId);
        $family    = $this->requireFamily($centre->getActiveAcademicYear(), $familyId);
        $programme = $this->requireProgramme($family, $programmeId);
        $level     = $this->requireLevel($programme, $levelId);
        $group     = $this->requireGroup($level, $groupId);

        $errors           = [];
        $values           = ['name' => $group->getName(), 'details' => $group->getDetails() ?? ''];
        $selectedTutor    = $group->getTutor();
        $selectedTeachers = $group->getTeachers()->toArray();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_group_' . $groupId, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'name'    => trim($request->request->getString('name')),
                'details' => trim($request->request->getString('details')),
            ];

            if ($values['name'] === '') {
                $errors['name'] = $this->t('group.error.name_required');
            }

            $tutorId    = trim($request->request->getString('tutor'));
            $tutor      = $tutorId !== '' ? $this->teachers->findById($tutorId) : null;
            $teacherIds = array_values(array_filter(
                array_map(
                    static fn(mixed $v): string => \is_string($v) ? $v : '',
                    $request->request->all('teachers'),
                ),
                static fn(string $v): bool => $v !== '',
            ));

            if (!empty($errors)) {
                $selectedTutor    = $tutor;
                $selectedTeachers = array_filter(
                    array_map(fn(string $id) => $this->teachers->findById($id), $teacherIds),
                );
            } else {
                $group->setName($values['name'])
                    ->setDetails($values['details'] !== '' ? $values['details'] : null)
                    ->setTutor($tutor);

                foreach ($group->getTeachers()->toArray() as $t) {
                    $group->removeTeacher($t);
                }
                foreach ($teacherIds as $id) {
                    $teacher = $this->teachers->findById($id);
                    if ($teacher !== null) {
                        $group->addTeacher($teacher);
                    }
                }

                $this->em->flush();
                $this->addFlash('success', $this->t('group.flash.saved'));

                return $this->redirectToRoute('app_admin_groups_edit', [
                    'centreId'    => $centreId,
                    'familyId'    => $familyId,
                    'programmeId' => $programmeId,
                    'levelId'     => $levelId,
                    'groupId'     => $groupId,
                ]);
            }
        }

        return $this->render('admin/group/edit.html.twig', [
            'centre'           => $centre,
            'family'           => $family,
            'programme'        => $programme,
            'level'            => $level,
            'group'            => $group,
            'errors'           => $errors,
            'values'           => $values,
            'selectedTutor'    => $selectedTutor,
            'selectedTeachers' => $selectedTeachers,
        ]);
    }

    #[Route('/{familyId}/enseñanzas/{programmeId}/niveles/{levelId}/grupos/{groupId}/eliminar', name: 'app_admin_groups_delete', methods: ['POST'])]
    public function deleteGroup(
        string $centreId,
        string $familyId,
        string $programmeId,
        string $levelId,
        string $groupId,
        Request $request,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_group_' . $groupId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $centre    = $this->requireCentreWithActiveYear($centreId);
        $family    = $this->requireFamily($centre->getActiveAcademicYear(), $familyId);
        $programme = $this->requireProgramme($family, $programmeId);
        $level     = $this->requireLevel($programme, $levelId);
        $group     = $this->requireGroup($level, $groupId);

        try {
            $this->em->remove($group);
            $this->em->flush();
            $this->addFlash('success', $this->t('group.flash.deleted'));
        } catch (\Exception) {
            $this->addFlash('error', $this->t('group.flash.delete_error'));
        }

        return $this->redirectToRoute('app_admin_levels_edit', [
            'centreId'    => $centreId,
            'familyId'    => $familyId,
            'programmeId' => $programmeId,
            'levelId'     => $levelId,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireCentre(string $centreId): EducationalCentre
    {
        $centre = $this->centres->findByIdWithActiveYear($centreId);
        if ($centre === null) {
            throw $this->createNotFoundException();
        }

        return $centre;
    }

    private function requireCentreWithActiveYear(string $centreId): EducationalCentre
    {
        $centre = $this->requireCentre($centreId);
        if ($centre->getActiveAcademicYear() === null) {
            throw $this->createNotFoundException();
        }

        return $centre;
    }

    private function requireFamily(AcademicYear $year, string $familyId): ProfessionalFamily
    {
        $family = $this->families->findByYearAndId($year, $familyId);
        if ($family === null) {
            throw $this->createNotFoundException();
        }

        return $family;
    }

    private function requireProgramme(ProfessionalFamily $family, string $programmeId): Programme
    {
        $programme = $this->programmes->findByFamilyAndId($family, $programmeId);
        if ($programme === null) {
            throw $this->createNotFoundException();
        }

        return $programme;
    }

    private function requireLevel(Programme $programme, string $levelId): ProgrammeYear
    {
        $level = $this->levels->findByProgrammeAndId($programme, $levelId);
        if ($level === null) {
            throw $this->createNotFoundException();
        }

        return $level;
    }

    private function requireGroup(ProgrammeYear $level, string $groupId): Group
    {
        $group = $this->groups->findByLevelAndId($level, $groupId);
        if ($group === null) {
            throw $this->createNotFoundException();
        }

        return $group;
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'admin');
    }
}
