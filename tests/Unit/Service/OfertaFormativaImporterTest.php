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
use App\Repository\TeacherRepository;
use App\Service\ImportOptions;
use App\Service\OfertaFormativaImporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[AllowMockObjectsWithoutExpectations]
class OfertaFormativaImporterTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ProfessionalFamilyRepository&Stub $familyRepo;
    private ProgrammeRepository&Stub $programmeRepo;
    private ProgrammeYearRepository&Stub $levelRepo;
    private GroupRepository&Stub $groupRepo;
    private TeacherRepository&MockObject $teacherRepo;
    private OfertaFormativaImporter $importer;
    private AcademicYear $year;

    protected function setUp(): void
    {
        $this->em            = $this->createMock(EntityManagerInterface::class);
        $this->familyRepo    = $this->createStub(ProfessionalFamilyRepository::class);
        $this->programmeRepo = $this->createStub(ProgrammeRepository::class);
        $this->levelRepo     = $this->createStub(ProgrammeYearRepository::class);
        $this->groupRepo     = $this->createStub(GroupRepository::class);
        $this->teacherRepo   = $this->createMock(TeacherRepository::class);

        $this->importer = new OfertaFormativaImporter(
            $this->em,
            $this->familyRepo,
            $this->programmeRepo,
            $this->levelRepo,
            $this->groupRepo,
            $this->teacherRepo,
        );

        $this->year = new AcademicYear();
        $this->setId($this->year);
    }

    // ── Structure creation ────────────────────────────────────────────────────

    public function testImportCreatesNewFamily(): void
    {
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([]);
        $this->em->expects(self::atLeastOnce())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $stats = $this->importer->import(
            ['families' => [['name' => 'Informática', 'programmes' => []]]],
            $this->year,
            new ImportOptions(),
        );

        self::assertSame(1, $stats['families']);
    }

    public function testImportCreatesFullNestedStructure(): void
    {
        $this->setUpEmptyRepositories();

        $stats = $this->importer->import(
            $this->buildData(['Informática' => ['DAW' => ['1º DAW' => ['DAW1A', 'DAW1B']]]]),
            $this->year,
            new ImportOptions(),
        );

        self::assertSame(1, $stats['families']);
        self::assertSame(1, $stats['programmes']);
        self::assertSame(1, $stats['levels']);
        self::assertSame(2, $stats['groups']);
    }

    public function testImportSkipsEmptyFamilyName(): void
    {
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([]);
        $this->em->expects(self::never())->method('persist');

        $stats = $this->importer->import(
            ['families' => [['name' => '   ', 'programmes' => []]]],
            $this->year,
            new ImportOptions(),
        );

        self::assertSame(0, $stats['families']);
    }

    public function testImportSkipsEmptyProgrammeName(): void
    {
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([]);

        $stats = $this->importer->import(
            ['families' => [['name' => 'Informática', 'programmes' => [['name' => '']]]]],
            $this->year,
            new ImportOptions(),
        );

        self::assertSame(0, $stats['programmes']);
    }

    public function testImportSkipsEmptyLevelName(): void
    {
        $this->setUpEmptyRepositories();

        $data = $this->buildData(['Informática' => ['DAW' => ['' => ['DAW1A']]]]);
        $stats = $this->importer->import($data, $this->year, new ImportOptions());

        self::assertSame(0, $stats['levels']);
        self::assertSame(0, $stats['groups']);
    }

    public function testImportSkipsEmptyGroupName(): void
    {
        $this->setUpEmptyRepositories();

        $data = $this->buildData(['Informática' => ['DAW' => ['1º DAW' => ['']]]]);
        $stats = $this->importer->import($data, $this->year, new ImportOptions());

        self::assertSame(0, $stats['groups']);
    }

    // ── No duplicates for programmes, levels and groups ───────────────────────

    public function testImportDoesNotDuplicateExistingFamilyByName(): void
    {
        $existing = $this->makeFamily('Informática');
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$existing]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([]);

        $this->em->expects(self::never())->method('persist');

        $stats = $this->importer->import(
            ['families' => [['name' => 'Informática', 'programmes' => []]]],
            $this->year,
            new ImportOptions(),
        );

        self::assertSame(0, $stats['families']);
    }

    public function testImportDoesNotDuplicateExistingProgrammeByName(): void
    {
        $family    = $this->makeFamily('Informática');
        $programme = $this->makeProgramme('DAW', $family);
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$family]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([$programme]);
        $this->levelRepo->method('findByProgrammeOrderedByName')->willReturn([]);

        $stats = $this->importer->import(
            ['families' => [['name' => 'Informática', 'programmes' => [['name' => 'DAW', 'levels' => []]]]]],
            $this->year,
            new ImportOptions(),
        );

        self::assertSame(0, $stats['programmes']);
    }

    public function testImportDoesNotDuplicateExistingLevelByName(): void
    {
        $family    = $this->makeFamily('Informática');
        $programme = $this->makeProgramme('DAW', $family);
        $level     = $this->makeLevel('1º DAW', $programme);
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$family]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([$programme]);
        $this->levelRepo->method('findByProgrammeOrderedByName')->willReturn([$level]);
        $this->groupRepo->method('findByLevelOrderedByName')->willReturn([]);

        $stats = $this->importer->import(
            ['families' => [[
                'name' => 'Informática',
                'programmes' => [['name' => 'DAW', 'levels' => [['name' => '1º DAW', 'groups' => []]]]],
            ]]],
            $this->year,
            new ImportOptions(),
        );

        self::assertSame(0, $stats['levels']);
    }

    public function testImportDoesNotDuplicateExistingGroupByName(): void
    {
        $family    = $this->makeFamily('Informática');
        $programme = $this->makeProgramme('DAW', $family);
        $level     = $this->makeLevel('1º DAW', $programme);
        $group     = $this->makeGroup('DAW1A', $level);
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$family]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([$programme]);
        $this->levelRepo->method('findByProgrammeOrderedByName')->willReturn([$level]);
        $this->groupRepo->method('findByLevelOrderedByName')->willReturn([$group]);

        $stats = $this->importer->import(
            ['families' => [[
                'name' => 'Informática',
                'programmes' => [[
                    'name'   => 'DAW',
                    'levels' => [['name' => '1º DAW', 'groups' => [['name' => 'DAW1A', 'teachers' => [], 'tutors' => []]]]],
                ]],
            ]]],
            $this->year,
            new ImportOptions(),
        );

        self::assertSame(0, $stats['groups']);
    }

    public function testImportMatchesNamesCaseInsensitively(): void
    {
        $existing = $this->makeFamily('INFORMÁTICA');
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([$existing]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([]);

        $this->em->expects(self::never())->method('persist');

        $stats = $this->importer->import(
            ['families' => [['name' => 'informática', 'programmes' => []]]],
            $this->year,
            new ImportOptions(),
        );

        self::assertSame(0, $stats['families']);
    }

    // ── ImportOptions: heads ──────────────────────────────────────────────────

    public function testImportAssignsHeadWhenOptionEnabled(): void
    {
        $teacher = $this->makeTeacher('jefa.dpto');
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([]);
        $this->teacherRepo->expects(self::once())->method('findByUsername')->with('jefa.dpto')->willReturn($teacher);

        $capturedFamily = null;
        $this->em->method('persist')->willReturnCallback(
            function (object $entity) use (&$capturedFamily): void {
                if ($entity instanceof ProfessionalFamily) {
                    $capturedFamily = $entity;
                }
            }
        );

        $this->importer->import(
            ['families' => [['name' => 'Informática', 'head' => 'jefa.dpto', 'programmes' => []]]],
            $this->year,
            new ImportOptions(importHeads: true),
        );

        self::assertInstanceOf(ProfessionalFamily::class, $capturedFamily);
        self::assertSame($teacher, $capturedFamily->getHead());
    }

    public function testImportIgnoresHeadWhenOptionDisabled(): void
    {
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([]);
        $this->teacherRepo->expects(self::never())->method('findByUsername');

        $this->importer->import(
            ['families' => [['name' => 'Informática', 'head' => 'jefa.dpto', 'programmes' => []]]],
            $this->year,
            new ImportOptions(importHeads: false),
        );
    }

    public function testImportReportsHeadUsernameAsMissingWhenNotFound(): void
    {
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([]);
        $this->teacherRepo->method('findByUsername')->willReturn(null);

        $stats = $this->importer->import(
            ['families' => [['name' => 'Informática', 'head' => 'nobody', 'programmes' => []]]],
            $this->year,
            new ImportOptions(importHeads: true),
        );

        self::assertContains('nobody', $stats['missing_teachers']);
    }

    // ── ImportOptions: group teachers ─────────────────────────────────────────

    public function testImportAssignsGroupTeachersWhenOptionEnabled(): void
    {
        $teacher = $this->makeTeacher('jdoe');
        $this->setUpEmptyRepositories();
        $this->teacherRepo->expects(self::once())->method('findByUsername')->with('jdoe')->willReturn($teacher);

        $capturedGroup = null;
        $this->em->method('persist')->willReturnCallback(
            function (object $entity) use (&$capturedGroup): void {
                if ($entity instanceof Group) {
                    $capturedGroup = $entity;
                }
            }
        );

        $data = $this->buildData(['Informática' => ['DAW' => ['1º DAW' => ['DAW1A']]]]);
        $data['families'][0]['programmes'][0]['levels'][0]['groups'][0]['teachers'] = ['jdoe'];

        $this->importer->import($data, $this->year, new ImportOptions(importTeachers: true));

        self::assertInstanceOf(Group::class, $capturedGroup);
        self::assertCount(1, $capturedGroup->getTeachers());
        self::assertSame($teacher, $capturedGroup->getTeachers()->first());
    }

    public function testImportIgnoresGroupTeachersWhenOptionDisabled(): void
    {
        $this->setUpEmptyRepositories();
        $this->teacherRepo->expects(self::never())->method('findByUsername');

        $capturedGroup = null;
        $this->em->method('persist')->willReturnCallback(
            function (object $entity) use (&$capturedGroup): void {
                if ($entity instanceof Group) {
                    $capturedGroup = $entity;
                }
            }
        );

        $data = $this->buildData(['Informática' => ['DAW' => ['1º DAW' => ['DAW1A']]]]);
        $data['families'][0]['programmes'][0]['levels'][0]['groups'][0]['teachers'] = ['jdoe'];

        $this->importer->import($data, $this->year, new ImportOptions(importTeachers: false));

        self::assertInstanceOf(Group::class, $capturedGroup);
        self::assertCount(0, $capturedGroup->getTeachers());
    }

    public function testImportReportsTeacherUsernameAsMissingWhenNotFound(): void
    {
        $this->setUpEmptyRepositories();
        $this->teacherRepo->method('findByUsername')->willReturn(null);

        $data = $this->buildData(['Informática' => ['DAW' => ['1º DAW' => ['DAW1A']]]]);
        $data['families'][0]['programmes'][0]['levels'][0]['groups'][0]['teachers'] = ['ghost'];

        $stats = $this->importer->import($data, $this->year, new ImportOptions(importTeachers: true));

        self::assertContains('ghost', $stats['missing_teachers']);
    }

    public function testImportDeduplicatesTeacherUsernamesInSameList(): void
    {
        $teacher = $this->makeTeacher('jdoe');
        $this->setUpEmptyRepositories();
        $this->teacherRepo->method('findByUsername')->willReturn($teacher);

        $capturedGroup = null;
        $this->em->method('persist')->willReturnCallback(
            function (object $entity) use (&$capturedGroup): void {
                if ($entity instanceof Group) {
                    $capturedGroup = $entity;
                }
            }
        );

        $data = $this->buildData(['Informática' => ['DAW' => ['1º DAW' => ['DAW1A']]]]);
        $data['families'][0]['programmes'][0]['levels'][0]['groups'][0]['teachers'] = ['jdoe', 'jdoe'];

        $stats = $this->importer->import($data, $this->year, new ImportOptions(importTeachers: true));

        self::assertInstanceOf(Group::class, $capturedGroup);
        self::assertCount(1, $capturedGroup->getTeachers());
        self::assertEmpty($stats['missing_teachers']);
    }

    // ── ImportOptions: group tutors ───────────────────────────────────────────

    public function testImportAssignsGroupTutorsWhenOptionEnabled(): void
    {
        $teacher = $this->makeTeacher('tutor.uno');
        $this->setUpEmptyRepositories();
        $this->teacherRepo->expects(self::once())->method('findByUsername')->with('tutor.uno')->willReturn($teacher);

        $capturedGroup = null;
        $this->em->method('persist')->willReturnCallback(
            function (object $entity) use (&$capturedGroup): void {
                if ($entity instanceof Group) {
                    $capturedGroup = $entity;
                }
            }
        );

        $data = $this->buildData(['Informática' => ['DAW' => ['1º DAW' => ['DAW1A']]]]);
        $data['families'][0]['programmes'][0]['levels'][0]['groups'][0]['tutors'] = ['tutor.uno'];

        $this->importer->import($data, $this->year, new ImportOptions(importTutors: true));

        self::assertInstanceOf(Group::class, $capturedGroup);
        self::assertCount(1, $capturedGroup->getTutors());
        self::assertSame($teacher, $capturedGroup->getTutors()->first());
    }

    public function testImportIgnoresGroupTutorsWhenOptionDisabled(): void
    {
        $this->setUpEmptyRepositories();
        $this->teacherRepo->expects(self::never())->method('findByUsername');

        $capturedGroup = null;
        $this->em->method('persist')->willReturnCallback(
            function (object $entity) use (&$capturedGroup): void {
                if ($entity instanceof Group) {
                    $capturedGroup = $entity;
                }
            }
        );

        $data = $this->buildData(['Informática' => ['DAW' => ['1º DAW' => ['DAW1A']]]]);
        $data['families'][0]['programmes'][0]['levels'][0]['groups'][0]['tutors'] = ['tutor.uno'];

        $this->importer->import($data, $this->year, new ImportOptions(importTutors: false));

        self::assertInstanceOf(Group::class, $capturedGroup);
        self::assertCount(0, $capturedGroup->getTutors());
    }

    public function testImportReportsTutorUsernameAsMissingWhenNotFound(): void
    {
        $this->setUpEmptyRepositories();
        $this->teacherRepo->method('findByUsername')->willReturn(null);

        $data = $this->buildData(['Informática' => ['DAW' => ['1º DAW' => ['DAW1A']]]]);
        $data['families'][0]['programmes'][0]['levels'][0]['groups'][0]['tutors'] = ['phantom'];

        $stats = $this->importer->import($data, $this->year, new ImportOptions(importTutors: true));

        self::assertContains('phantom', $stats['missing_teachers']);
    }

    public function testImportDeduplicatesTutorUsernamesInSameList(): void
    {
        $teacher = $this->makeTeacher('tutor.uno');
        $this->setUpEmptyRepositories();
        $this->teacherRepo->method('findByUsername')->willReturn($teacher);

        $capturedGroup = null;
        $this->em->method('persist')->willReturnCallback(
            function (object $entity) use (&$capturedGroup): void {
                if ($entity instanceof Group) {
                    $capturedGroup = $entity;
                }
            }
        );

        $data = $this->buildData(['Informática' => ['DAW' => ['1º DAW' => ['DAW1A']]]]);
        $data['families'][0]['programmes'][0]['levels'][0]['groups'][0]['tutors'] = ['tutor.uno', 'tutor.uno'];

        $stats = $this->importer->import($data, $this->year, new ImportOptions(importTutors: true));

        self::assertInstanceOf(Group::class, $capturedGroup);
        self::assertCount(1, $capturedGroup->getTutors());
        self::assertEmpty($stats['missing_teachers']);
    }

    // ── Missing teachers across options ──────────────────────────────────────

    public function testMissingTeachersAreDeduplicatedAndSorted(): void
    {
        $this->setUpEmptyRepositories();
        $this->teacherRepo->method('findByUsername')->willReturn(null);

        $data = $this->buildData(['Informática' => ['DAW' => ['1º DAW' => ['DAW1A']]]]);
        $data['families'][0]['head'] = 'zuser';
        $data['families'][0]['programmes'][0]['levels'][0]['groups'][0]['teachers'] = ['zuser', 'auser'];
        $data['families'][0]['programmes'][0]['levels'][0]['groups'][0]['tutors']   = ['zuser', 'auser'];

        $stats = $this->importer->import(
            $data,
            $this->year,
            new ImportOptions(importHeads: true, importTeachers: true, importTutors: true),
        );

        self::assertSame(['auser', 'zuser'], $stats['missing_teachers']);
    }

    // ── Flush ─────────────────────────────────────────────────────────────────

    public function testFlushIsCalledExactlyOnce(): void
    {
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([]);
        $this->em->expects(self::once())->method('flush');

        $this->importer->import(['families' => []], $this->year, new ImportOptions());
    }

    public function testFlushIsCalledEvenWithEmptyData(): void
    {
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([]);
        $this->em->expects(self::once())->method('flush');

        $this->importer->import([], $this->year, new ImportOptions());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function setUpEmptyRepositories(): void
    {
        $this->familyRepo->method('findByAcademicYearFiltered')->willReturn([]);
        $this->programmeRepo->method('findByFamilyOrderedByName')->willReturn([]);
        $this->levelRepo->method('findByProgrammeOrderedByName')->willReturn([]);
        $this->groupRepo->method('findByLevelOrderedByName')->willReturn([]);
    }

    /**
     * Builds minimal JSON data.
     * $structure: familyName → [programmeName → [levelName → [groupName, ...]]]
     *
     * @param array<string, array<string, array<string, list<string>>>> $structure
     * @return array<string, mixed>
     */
    private function buildData(array $structure): array
    {
        $families = [];
        foreach ($structure as $familyName => $programmes) {
            $progs = [];
            foreach ($programmes as $progName => $levels) {
                $lvls = [];
                foreach ($levels as $levelName => $groups) {
                    $grps = [];
                    foreach ($groups as $groupName) {
                        $grps[] = ['name' => $groupName, 'details' => null, 'teachers' => [], 'tutors' => []];
                    }
                    $lvls[] = ['name' => $levelName, 'details' => null, 'groups' => $grps];
                }
                $progs[] = ['name' => $progName, 'details' => null, 'coordinators' => [], 'levels' => $lvls];
            }
            $families[] = ['name' => $familyName, 'head' => null, 'programmes' => $progs];
        }

        return ['families' => $families];
    }

    private function makeFamily(string $name): ProfessionalFamily
    {
        $family = (new ProfessionalFamily())->setName($name)->setAcademicYear($this->year);
        $this->setId($family);

        return $family;
    }

    private function makeProgramme(string $name, ProfessionalFamily $family): Programme
    {
        $prog = (new Programme())->setName($name)->setProfessionalFamily($family)->setAcademicYear($this->year);
        $this->setId($prog);

        return $prog;
    }

    private function makeLevel(string $name, Programme $programme): ProgrammeYear
    {
        $level = (new ProgrammeYear())->setName($name)->setProgramme($programme);
        $this->setId($level);

        return $level;
    }

    private function makeGroup(string $name, ProgrammeYear $level): Group
    {
        $group = (new Group())->setName($name)->setProgrammeYear($level);
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
