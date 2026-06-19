<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\AcademicYear;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Teacher;
use App\Repository\GroupRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\ProgrammeYearRepository;
use App\Service\OfertaFormativaExporter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class OfertaFormativaExporterTest extends TestCase
{
    private ProfessionalFamilyRepository&MockObject $familyRepo;
    private ProgrammeRepository&MockObject $programmeRepo;
    private ProgrammeYearRepository&MockObject $levelRepo;
    private GroupRepository&MockObject $groupRepo;
    private OfertaFormativaExporter $exporter;
    private AcademicYear $year;

    protected function setUp(): void
    {
        $this->familyRepo    = $this->createMock(ProfessionalFamilyRepository::class);
        $this->programmeRepo = $this->createMock(ProgrammeRepository::class);
        $this->levelRepo     = $this->createMock(ProgrammeYearRepository::class);
        $this->groupRepo     = $this->createMock(GroupRepository::class);

        $this->exporter = new OfertaFormativaExporter(
            $this->familyRepo,
            $this->programmeRepo,
            $this->levelRepo,
            $this->groupRepo,
        );

        $this->year = new AcademicYear();
        $this->year->setName('2024-2025');
        $this->setId($this->year);
    }

    public function testExportContainsAcademicYearName(): void
    {
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([]);

        $data = $this->exporter->export($this->year);

        self::assertSame('2024-2025', $data['academic_year']);
    }

    public function testExportContainsExportedAtTimestamp(): void
    {
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([]);

        $data = $this->exporter->export($this->year);

        self::assertArrayHasKey('exported_at', $data);
        self::assertNotEmpty($data['exported_at']);
    }

    public function testExportReturnsEmptyFamiliesWhenNoneExist(): void
    {
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([]);

        $data = $this->exporter->export($this->year);

        self::assertSame([], $data['families']);
    }

    public function testExportIncludesFamilyNameAndNullHead(): void
    {
        $family = $this->makeFamily('Informática', null);
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$family]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([]);

        $data = $this->exporter->export($this->year);

        self::assertCount(1, $data['families']);
        self::assertSame('Informática', $data['families'][0]['name']);
        self::assertNull($data['families'][0]['head']);
    }

    public function testExportIncludesFamilyHeadUsername(): void
    {
        $head   = $this->makeTeacher('jefa.dpto');
        $family = $this->makeFamily('Informática', $head);
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$family]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([]);

        $data = $this->exporter->export($this->year);

        self::assertSame('jefa.dpto', $data['families'][0]['head']);
    }

    public function testExportIncludesProgrammeWithDetails(): void
    {
        $family    = $this->makeFamily('Informática', null);
        $programme = $this->makeProgramme('DAW', 'Desarrollo de aplicaciones web', $family, []);
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$family]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([$programme]);
        $this->levelRepo->method('findByProgrammeOrderedByName')->willReturn([]);

        $data = $this->exporter->export($this->year);

        $prog = $data['families'][0]['programmes'][0];
        self::assertSame('DAW', $prog['name']);
        self::assertSame('Desarrollo de aplicaciones web', $prog['details']);
    }

    public function testExportIncludesProgrammeCoordinatorUsernames(): void
    {
        $c1     = $this->makeTeacher('coord.uno');
        $c2     = $this->makeTeacher('coord.dos');
        $family = $this->makeFamily('Informática', null);
        $prog   = $this->makeProgramme('DAW', null, $family, [$c1, $c2]);
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$family]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([$prog]);
        $this->levelRepo->method('findByProgrammeOrderedByName')->willReturn([]);

        $data = $this->exporter->export($this->year);

        $coords = $data['families'][0]['programmes'][0]['coordinators'];
        self::assertContains('coord.uno', $coords);
        self::assertContains('coord.dos', $coords);
    }

    public function testExportSortsProgrammeCoordinators(): void
    {
        $family = $this->makeFamily('Informática', null);
        $prog   = $this->makeProgramme('DAW', null, $family, [
            $this->makeTeacher('zuser'),
            $this->makeTeacher('auser'),
        ]);
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$family]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([$prog]);
        $this->levelRepo->method('findByProgrammeOrderedByName')->willReturn([]);

        $data = $this->exporter->export($this->year);

        self::assertSame(['auser', 'zuser'], $data['families'][0]['programmes'][0]['coordinators']);
    }

    public function testExportIncludesLevelAndGroup(): void
    {
        $family    = $this->makeFamily('Informática', null);
        $programme = $this->makeProgramme('DAW', null, $family, []);
        $level     = $this->makeLevel('1º DAW', null, $programme);
        $group     = $this->makeGroup('DAW1A', null, $level, [], []);
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$family]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([$programme]);
        $this->levelRepo->method('findByProgrammeOrderedByName')->willReturn([$level]);
        $this->groupRepo->method('findByLevelOrderedByName')->willReturn([$group]);

        $data = $this->exporter->export($this->year);

        $lvl = $data['families'][0]['programmes'][0]['levels'][0];
        self::assertSame('1º DAW', $lvl['name']);
        self::assertSame('DAW1A', $lvl['groups'][0]['name']);
    }

    public function testExportIncludesGroupTeacherAndTutorUsernames(): void
    {
        $t1     = $this->makeTeacher('teacher.one');
        $t2     = $this->makeTeacher('tutor.one');
        $family = $this->makeFamily('Informática', null);
        $prog   = $this->makeProgramme('DAW', null, $family, []);
        $level  = $this->makeLevel('1º DAW', null, $prog);
        $group  = $this->makeGroup('DAW1A', null, $level, [$t1], [$t2]);
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$family]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([$prog]);
        $this->levelRepo->method('findByProgrammeOrderedByName')->willReturn([$level]);
        $this->groupRepo->method('findByLevelOrderedByName')->willReturn([$group]);

        $data = $this->exporter->export($this->year);

        $grp = $data['families'][0]['programmes'][0]['levels'][0]['groups'][0];
        self::assertSame(['teacher.one'], $grp['teachers']);
        self::assertSame(['tutor.one'], $grp['tutors']);
    }

    public function testExportSortsGroupTeachersAndTutors(): void
    {
        $family = $this->makeFamily('Informática', null);
        $prog   = $this->makeProgramme('DAW', null, $family, []);
        $level  = $this->makeLevel('1º DAW', null, $prog);
        $group  = $this->makeGroup('DAW1A', null, $level,
            [$this->makeTeacher('z.teacher'), $this->makeTeacher('a.teacher')],
            [$this->makeTeacher('z.tutor'), $this->makeTeacher('a.tutor')],
        );
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$family]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([$prog]);
        $this->levelRepo->method('findByProgrammeOrderedByName')->willReturn([$level]);
        $this->groupRepo->method('findByLevelOrderedByName')->willReturn([$group]);

        $data = $this->exporter->export($this->year);

        $grp = $data['families'][0]['programmes'][0]['levels'][0]['groups'][0];
        self::assertSame(['a.teacher', 'z.teacher'], $grp['teachers']);
        self::assertSame(['a.tutor', 'z.tutor'], $grp['tutors']);
    }

    public function testExportGroupNullDetails(): void
    {
        $family = $this->makeFamily('Informática', null);
        $prog   = $this->makeProgramme('DAW', null, $family, []);
        $level  = $this->makeLevel('1º DAW', null, $prog);
        $group  = $this->makeGroup('DAW1A', null, $level, [], []);
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$family]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([$prog]);
        $this->levelRepo->method('findByProgrammeOrderedByName')->willReturn([$level]);
        $this->groupRepo->method('findByLevelOrderedByName')->willReturn([$group]);

        $data = $this->exporter->export($this->year);

        self::assertNull($data['families'][0]['programmes'][0]['levels'][0]['groups'][0]['details']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeFamily(string $name, ?Teacher $head): ProfessionalFamily
    {
        $family = (new ProfessionalFamily())->setName($name)->setAcademicYear($this->year)->setHead($head);
        $this->setId($family);

        return $family;
    }

    /** @param list<Teacher> $coordinators */
    private function makeProgramme(string $name, ?string $details, ProfessionalFamily $family, array $coordinators): Programme
    {
        $prog = (new Programme())->setName($name)->setDetails($details)->setProfessionalFamily($family)->setAcademicYear($this->year);
        foreach ($coordinators as $c) {
            $prog->addCoordinator($c);
        }
        $this->setId($prog);

        return $prog;
    }

    private function makeLevel(string $name, ?string $details, Programme $programme): ProgrammeYear
    {
        $level = (new ProgrammeYear())->setName($name)->setDetails($details)->setProgramme($programme);
        $this->setId($level);

        return $level;
    }

    /**
     * @param list<Teacher> $teachers
     * @param list<Teacher> $tutors
     */
    private function makeGroup(string $name, ?string $details, ProgrammeYear $level, array $teachers, array $tutors): Group
    {
        $group = (new Group())->setName($name)->setDetails($details)->setProgrammeYear($level);
        foreach ($teachers as $t) {
            $group->addTeacher($t);
        }
        foreach ($tutors as $t) {
            $group->addTutor($t);
        }
        $this->setId($group);

        return $group;
    }

    private function makeTeacher(string $username): Teacher
    {
        $teacher = (new Teacher(new PersonName('Test', 'User')))->setUsername($username);
        $this->setId($teacher);

        return $teacher;
    }

    private function setId(object $entity): void
    {
        $class = new \ReflectionClass($entity);
        while (!$class->hasProperty('id')) {
            $class = $class->getParentClass();
        }
        $class->getProperty('id')->setValue($entity, Uuid::v7());
    }
}
