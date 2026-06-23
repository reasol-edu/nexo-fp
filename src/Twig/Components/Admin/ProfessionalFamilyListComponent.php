<?php

declare(strict_types=1);

namespace App\Twig\Components\Admin;

use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Teacher;
use App\Repository\GroupRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\ProgrammeYearRepository;
use App\Repository\TeacherRepository;
use App\Security\Voter\EducationalCentreVoter;
use App\Service\TenantContext;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Column navigation (Miller columns) for the formative offer:
 * Families → Programmes → Levels → Groups, fully inline with no page reloads.
 */
#[AsLiveComponent]
class ProfessionalFamilyListComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public EducationalCentre $centre;

    #[LiveProp(writable: true)]
    public string $familyId = '';

    #[LiveProp(writable: true)]
    public string $programmeId = '';

    #[LiveProp(writable: true)]
    public string $levelId = '';

    #[LiveProp(writable: true)]
    public string $groupId = '';

    /** Inline "add" inputs, one per column. */
    #[LiveProp(writable: true)]
    public string $addFamilyName = '';

    #[LiveProp(writable: true)]
    public string $addProgrammeName = '';

    #[LiveProp(writable: true)]
    public string $addLevelName = '';

    #[LiveProp(writable: true)]
    public string $addGroupName = '';

    /** Detail-panel fields for the deepest selected item. */
    #[LiveProp(writable: true)]
    public string $editName = '';

    #[LiveProp(writable: true)]
    public string $editDetails = '';

    /** @var array<string, string> */
    #[LiveProp]
    public array $errors = [];

    /** Inline two-step confirmation for deleting the selected item. */
    #[LiveProp(writable: true)]
    public bool $confirmingDelete = false;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly ProfessionalFamilyRepository $families,
        private readonly ProgrammeRepository $programmes,
        private readonly ProgrammeYearRepository $levels,
        private readonly GroupRepository $groups,
        private readonly TeacherRepository $teachers,
        private readonly TenantContext $tenantContext,
    ) {}

    public function mount(EducationalCentre $centre): void
    {
        $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);
        $this->centre = $centre;
    }

    public function canWrite(): bool
    {
        return !$this->tenantContext->isViewingNonActiveYear($this->centre);
    }

    // ── Column data ──────────────────────────────────────────────────────────

    /** @return ProfessionalFamily[] */
    public function getFamilyList(): array
    {
        $year = $this->tenantContext->getViewYear($this->centre);
        if ($year === null) {
            return [];
        }

        return $this->families->findByAcademicYearFiltered($year);
    }

    /** @return array<string, int> */
    public function getFamilyCounts(): array
    {
        return $this->programmes->countByFamily($this->getFamilyList());
    }

    /** @return Programme[] */
    public function getProgrammeList(): array
    {
        $family = $this->getSelectedFamily();

        return $family === null ? [] : $this->programmes->findByFamilyOrderedByName($family);
    }

    /** @return array<string, int> */
    public function getProgrammeCounts(): array
    {
        return $this->levels->countByProgramme($this->getProgrammeList());
    }

    /** @return ProgrammeYear[] */
    public function getLevelList(): array
    {
        $programme = $this->getSelectedProgramme();

        return $programme === null ? [] : $this->levels->findByProgrammeOrderedByName($programme);
    }

    /** @return array<string, int> */
    public function getLevelCounts(): array
    {
        return $this->groups->countByLevel($this->getLevelList());
    }

    /** @return Group[] */
    public function getGroupList(): array
    {
        $level = $this->getSelectedLevel();

        return $level === null ? [] : $this->groups->findByLevelOrderedByName($level);
    }

    /** @return array<string, array{students: int, teachers: int}> */
    public function getGroupCounts(): array
    {
        $year = $this->tenantContext->getViewYear($this->centre);
        if ($year === null) {
            return [];
        }

        return $this->groups->findCountsByAcademicYear($year, $this->getGroupList());
    }

    /**
     * Viewed-year id used to scope the remote teacher autocomplete in the
     * detail panel. The staff selects fetch their options on demand from the
     * `teacher_centre` endpoint instead of rendering every teacher up front.
     */
    public function getViewYearId(): string
    {
        return $this->tenantContext->getViewYear($this->centre)?->getId()->toRfc4122() ?? '';
    }

    // ── Selected entities ────────────────────────────────────────────────────

    public function getSelectedFamily(): ?ProfessionalFamily
    {
        $year = $this->tenantContext->getViewYear($this->centre);
        if ($year === null || $this->familyId === '') {
            return null;
        }

        return $this->families->findByYearAndId($year, $this->familyId);
    }

    public function getSelectedProgramme(): ?Programme
    {
        $family = $this->getSelectedFamily();
        if ($family === null || $this->programmeId === '') {
            return null;
        }

        return $this->programmes->findByFamilyAndId($family, $this->programmeId);
    }

    public function getSelectedLevel(): ?ProgrammeYear
    {
        $programme = $this->getSelectedProgramme();
        if ($programme === null || $this->levelId === '') {
            return null;
        }

        return $this->levels->findByProgrammeAndId($programme, $this->levelId);
    }

    public function getSelectedGroup(): ?Group
    {
        $level = $this->getSelectedLevel();
        if ($level === null || $this->groupId === '') {
            return null;
        }

        return $this->groups->findByLevelAndId($level, $this->groupId);
    }

    /**
     * The deepest selected item, used by the detail panel.
     *
     * @return array{type: string, entity: ProfessionalFamily|Programme|ProgrammeYear|Group}|null
     */
    public function getSelected(): ?array
    {
        if (($group = $this->getSelectedGroup()) !== null) {
            return ['type' => 'group', 'entity' => $group];
        }
        if (($level = $this->getSelectedLevel()) !== null) {
            return ['type' => 'level', 'entity' => $level];
        }
        if (($programme = $this->getSelectedProgramme()) !== null) {
            return ['type' => 'programme', 'entity' => $programme];
        }
        if (($family = $this->getSelectedFamily()) !== null) {
            return ['type' => 'family', 'entity' => $family];
        }

        return null;
    }

    // ── Selection actions ────────────────────────────────────────────────────

    #[LiveAction]
    public function selectFamily(#[LiveArg] string $id): void
    {
        $this->familyId   = $id;
        $this->programmeId = $this->levelId = $this->groupId = '';
        $this->loadDetail();
    }

    #[LiveAction]
    public function selectProgramme(#[LiveArg] string $id): void
    {
        $this->programmeId = $id;
        $this->levelId = $this->groupId = '';
        $this->loadDetail();
    }

    #[LiveAction]
    public function selectLevel(#[LiveArg] string $id): void
    {
        $this->levelId = $id;
        $this->groupId = '';
        $this->loadDetail();
    }

    #[LiveAction]
    public function selectGroup(#[LiveArg] string $id): void
    {
        $this->groupId = $id;
        $this->loadDetail();
    }

    #[LiveAction]
    public function clearSelection(): void
    {
        $this->familyId = $this->programmeId = $this->levelId = $this->groupId = '';
        $this->errors = [];
    }

    private function loadDetail(): void
    {
        $this->errors = [];
        $this->confirmingDelete = false;
        $selected = $this->getSelected();
        if ($selected === null) {
            $this->editName = $this->editDetails = '';

            return;
        }

        $entity = $selected['entity'];
        $this->editName    = $entity->getName();
        $this->editDetails = $entity instanceof ProfessionalFamily ? '' : ($entity->getDetails() ?? '');
    }

    // ── Add actions ──────────────────────────────────────────────────────────

    #[LiveAction]
    public function addFamily(): void
    {
        $centre = $this->requireWritableCentre();
        $year   = $centre->getActiveAcademicYear();
        $name   = trim($this->addFamilyName);
        if ($year === null || $name === '') {
            return;
        }

        $family = (new ProfessionalFamily())
            ->setName($name)
            ->setAcademicYear($year)
            ->setHead(null);

        $this->em->persist($family);
        $this->em->flush();

        $this->addFamilyName = '';
        $this->selectFamily($family->getId()->toRfc4122());
    }

    #[LiveAction]
    public function addProgramme(): void
    {
        $centre = $this->requireWritableCentre();
        $year   = $centre->getActiveAcademicYear();
        $family = $this->getSelectedFamily();
        $name   = trim($this->addProgrammeName);
        if ($year === null || $family === null || $name === '') {
            return;
        }

        $programme = (new Programme())
            ->setName($name)
            ->setProfessionalFamily($family)
            ->setAcademicYear($year);

        $this->em->persist($programme);
        $this->em->flush();

        $this->addProgrammeName = '';
        $this->selectProgramme($programme->getId()->toRfc4122());
    }

    #[LiveAction]
    public function addLevel(): void
    {
        $this->requireWritableCentre();
        $programme = $this->getSelectedProgramme();
        $name      = trim($this->addLevelName);
        if ($programme === null || $name === '') {
            return;
        }

        $level = (new ProgrammeYear())
            ->setName($name)
            ->setProgramme($programme);

        $this->em->persist($level);
        $this->em->flush();

        $this->addLevelName = '';
        $this->selectLevel($level->getId()->toRfc4122());
    }

    #[LiveAction]
    public function addGroup(): void
    {
        $this->requireWritableCentre();
        $level = $this->getSelectedLevel();
        $name  = trim($this->addGroupName);
        if ($level === null || $name === '') {
            return;
        }

        $group = (new Group())
            ->setName($name)
            ->setProgrammeYear($level);

        $this->em->persist($group);
        $this->em->flush();

        $this->addGroupName = '';
        $this->selectGroup($group->getId()->toRfc4122());
    }

    // ── Detail save / delete ─────────────────────────────────────────────────

    #[LiveAction]
    public function saveDetail(): void
    {
        $this->requireWritableCentre();
        $selected = $this->getSelected();
        if ($selected === null) {
            return;
        }

        $name = trim($this->editName);
        if ($name === '') {
            $this->errors = ['name' => $this->t($selected['type'] . '.error.name_required')];

            return;
        }

        $entity  = $selected['entity'];
        $details = trim($this->editDetails);
        $entity->setName($name);
        if (!$entity instanceof ProfessionalFamily) {
            $entity->setDetails($details !== '' ? $details : null);
        }

        $this->em->flush();
        $this->errors = [];
        $this->addFlash('success', $this->t($selected['type'] . '.flash.saved'));
    }

    #[LiveAction]
    public function askDelete(): void
    {
        $this->confirmingDelete = true;
    }

    #[LiveAction]
    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    #[LiveAction]
    public function deleteSelected(): void
    {
        $this->requireWritableCentre();
        $this->confirmingDelete = false;
        $selected = $this->getSelected();
        if ($selected === null) {
            return;
        }

        $type = $selected['type'];
        try {
            $this->em->remove($selected['entity']);
            $this->em->flush();
            $this->addFlash('success', $this->t($type . '.flash.deleted'));
        } catch (\Exception) {
            $this->addFlash('error', $this->t($type . '.flash.delete_error'));

            return;
        }

        // Move the selection up one level.
        match ($type) {
            'group'     => $this->groupId = '',
            'level'     => $this->levelId = '',
            'programme' => $this->programmeId = '',
            default     => $this->familyId = '',
        };
        $this->loadDetail();
    }

    // ── Inline staff (head / coordinators / tutors / teachers) ───────────────

    #[LiveAction]
    public function setFamilyHead(#[LiveArg] string $teacherId): void
    {
        $this->requireWritableCentre();
        $family = $this->getSelectedFamily();
        if ($family === null) {
            return;
        }

        $family->setHead($teacherId === '' ? null : $this->resolveTeacher($teacherId));
        $this->em->flush();
        $this->addFlash('success', $this->t('family.flash.saved'));
    }

    /** @param string[] $ids */
    #[LiveAction]
    public function setCoordinators(#[LiveArg] array $ids): void
    {
        $this->requireWritableCentre();
        $programme = $this->getSelectedProgramme();
        if ($programme === null) {
            return;
        }

        $this->syncTeachers(
            $programme->getCoordinators(),
            $ids,
            fn (Teacher $t) => $programme->addCoordinator($t),
            fn (Teacher $t) => $programme->removeCoordinator($t),
        );
        $this->em->flush();
        $this->addFlash('success', $this->t('programme.flash.saved'));
    }

    /** @param string[] $ids */
    #[LiveAction]
    public function setGroupTutors(#[LiveArg] array $ids): void
    {
        $this->requireWritableCentre();
        $group = $this->getSelectedGroup();
        if ($group === null) {
            return;
        }

        $this->syncTeachers(
            $group->getTutors(),
            $ids,
            fn (Teacher $t) => $group->addTutor($t),
            fn (Teacher $t) => $group->removeTutor($t),
        );
        $this->em->flush();
        $this->addFlash('success', $this->t('group.flash.saved'));
    }

    /** @param string[] $ids */
    #[LiveAction]
    public function setGroupTeachers(#[LiveArg] array $ids): void
    {
        $this->requireWritableCentre();
        $group = $this->getSelectedGroup();
        if ($group === null) {
            return;
        }

        $this->syncTeachers(
            $group->getTeachers(),
            $ids,
            fn (Teacher $t) => $group->addTeacher($t),
            fn (Teacher $t) => $group->removeTeacher($t),
        );
        $this->em->flush();
        $this->addFlash('success', $this->t('group.flash.saved'));
    }

    private function resolveTeacher(string $id): ?Teacher
    {
        $year = $this->tenantContext->getViewYear($this->centre);

        return $year === null ? null : $this->teachers->findByAcademicYearAndId($year, $id);
    }

    /**
     * Reconcile a teacher collection against the desired set of ids.
     *
     * @param Collection<int, Teacher> $current
     * @param string[]                 $ids
     * @param callable(Teacher): mixed $add
     * @param callable(Teacher): mixed $remove
     */
    private function syncTeachers(Collection $current, array $ids, callable $add, callable $remove): void
    {
        $desired = [];
        foreach ($ids as $id) {
            if (Uuid::isValid($id) && ($teacher = $this->resolveTeacher($id)) !== null) {
                $desired[$teacher->getId()->toRfc4122()] = $teacher;
            }
        }

        foreach ($current as $teacher) {
            if (!isset($desired[$teacher->getId()->toRfc4122()])) {
                $remove($teacher);
            }
        }

        foreach ($desired as $key => $teacher) {
            if (!$current->exists(fn (int $i, Teacher $t) => $t->getId()->toRfc4122() === $key)) {
                $add($teacher);
            }
        }
    }

    private function requireWritableCentre(): EducationalCentre
    {
        $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $this->centre);
        if (!$this->canWrite() || $this->centre->getActiveAcademicYear() === null) {
            throw $this->createAccessDeniedException();
        }

        return $this->centre;
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'admin');
    }
}
