<?php

declare(strict_types=1);

namespace App\Twig\Components\Admin;

use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Repository\GroupRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\ProgrammeYearRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class ProfessionalFamilyListComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public EducationalCentre $centre;

    #[LiveProp(writable: true)]
    public string $search = '';

    public function __construct(
        private readonly ProfessionalFamilyRepository $families,
        private readonly ProgrammeRepository $programmes,
        private readonly ProgrammeYearRepository $levels,
        private readonly GroupRepository $groups,
    ) {}

    public function mount(EducationalCentre $centre): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->centre = $centre;
    }

    /**
     * @return list<array{
     *   family: ProfessionalFamily,
     *   programmes: list<array{
     *     programme: Programme,
     *     levels: list<array{level: ProgrammeYear, groups: list<Group>}>
     *   }>
     * }>
     */
    public function getTree(): array
    {
        $year = $this->centre->getActiveAcademicYear();
        if ($year === null) {
            return [];
        }

        $tree = [];
        foreach ($this->families->findByAcademicYearFiltered($year, trim($this->search)) as $family) {
            $familyNode = ['family' => $family, 'programmes' => []];
            foreach ($this->programmes->findByFamilyOrderedByName($family) as $programme) {
                $programmeNode = ['programme' => $programme, 'levels' => []];
                foreach ($this->levels->findByProgrammeOrderedByName($programme) as $level) {
                    $programmeNode['levels'][] = [
                        'level'  => $level,
                        'groups' => $this->groups->findByLevelOrderedByName($level),
                    ];
                }
                $familyNode['programmes'][] = $programmeNode;
            }
            $tree[] = $familyNode;
        }

        return $tree;
    }
}
