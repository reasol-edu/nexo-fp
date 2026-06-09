<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security\Voter;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\Workcenter;
use App\Security\Voter\StayVoter;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class StayVoterTest extends RepositoryTestCase
{
    private StayVoter $voter;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var StayVoter $voter */
        $voter       = self::getContainer()->get(StayVoter::class);
        $this->voter = $voter;
    }

    // ── MANAGE ───────────────────────────────────────────────────────────────

    public function testManageGrantedToGlobalAdmin(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000001');
        $admin = $this->makeTeacher('admin.1', admin: true);
        $this->persist($centre, $year, $family, $programme, $stay, $admin);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($admin), $stay, [StayVoter::MANAGE])
        );
    }

    public function testManageGrantedToCentreAdmin(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000002');
        $teacher = $this->makeTeacher('centre.admin.1');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher);
        $centre->addAdmin($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE])
        );
    }

    public function testManageGrantedToCoordinator(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000003');
        $teacher = $this->makeTeacher('coord.1');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher);
        $programme->addCoordinator($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE])
        );
    }

    public function testManageDeniedToLiaison(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000004');
        $teacher = $this->makeTeacher('liaison.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $company);
        $company->addLiaison($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE])
        );
    }

    public function testManageDeniedToUnrelatedTeacher(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000005');
        $teacher = $this->makeTeacher('unrelated.1');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE])
        );
    }

    public function testManageDeniedToCoordinatorOfDifferentProgramme(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000006');
        $family2    = $this->makeFamily($year, 'Sanidad');
        $programme2 = $this->makeProgramme($year, $family2, 'SMR');
        $teacher    = $this->makeTeacher('coord.other');
        $this->persist($centre, $year, $family, $programme, $stay, $family2, $programme2, $teacher);
        $programme2->addCoordinator($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE])
        );
    }

    public function testManageDeniedToLiaisonOfDifferentCentre(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000007');
        $centreB = $this->makeCentre('41000008');
        $teacher = $this->makeTeacher('liaison.other');
        $company = $this->makeCompany($centreB, 'Empresa en B S.L.');
        $this->persist($centre, $year, $family, $programme, $stay, $centreB, $teacher, $company);
        $company->addLiaison($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE])
        );
    }

    public function testManageGrantedToFamilyHead(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000016');
        $teacher = $this->makeTeacher('family.head.1');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher);
        $family->setHead($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE])
        );
    }

    public function testManageDeniedToFamilyHeadOfDifferentFamily(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000017');
        $family2 = $this->makeFamily($year, 'Sanidad');
        $teacher = $this->makeTeacher('family.head.other');
        $this->persist($centre, $year, $family, $programme, $stay, $family2, $teacher);
        $family2->setHead($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE])
        );
    }

    // ── CREATE ───────────────────────────────────────────────────────────────

    public function testCreateGrantedToGlobalAdmin(): void
    {
        $centre = $this->makeCentre('41000009');
        $admin  = $this->makeTeacher('admin.2', admin: true);
        $this->persist($centre, $admin);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($admin), $centre, [StayVoter::CREATE])
        );
    }

    public function testCreateGrantedToCentreAdmin(): void
    {
        $centre  = $this->makeCentre('41000010');
        $teacher = $this->makeTeacher('centre.admin.2');
        $this->persist($centre, $teacher);
        $centre->addAdmin($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $centre, [StayVoter::CREATE])
        );
    }

    public function testCreateGrantedToCoordinatorInCentre(): void
    {
        [$centre, $year, $programme, , $family] = $this->makeStayContext('41000011');
        $teacher = $this->makeTeacher('coord.create');
        $this->persist($centre, $year, $family, $programme, $teacher);
        $programme->addCoordinator($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $centre, [StayVoter::CREATE])
        );
    }

    public function testCreateDeniedToUnrelatedTeacher(): void
    {
        $centre  = $this->makeCentre('41000012');
        $teacher = $this->makeTeacher('unrelated.2');
        $this->persist($centre, $teacher);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $centre, [StayVoter::CREATE])
        );
    }

    public function testCreateDeniedToLiaisonAlone(): void
    {
        $centre  = $this->makeCentre('41000013');
        $teacher = $this->makeTeacher('liaison.create');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $teacher, $company);
        $company->addLiaison($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $centre, [StayVoter::CREATE])
        );
    }

    public function testCreateDeniedToFamilyHead(): void
    {
        [$centre, $year, $programme, , $family] = $this->makeStayContext('41000018');
        $teacher = $this->makeTeacher('family.head.create');
        $this->persist($centre, $year, $family, $programme, $teacher);
        $family->setHead($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $centre, [StayVoter::CREATE])
        );
    }

    public function testCreateDeniedToCoordinatorOfDifferentCentre(): void
    {
        $centreA  = $this->makeCentre('41000014');
        $centreB  = $this->makeCentre('41000015');
        $yearA    = $this->makeYear($centreA);
        $familyA  = $this->makeFamily($yearA, 'Informática');
        $progA    = $this->makeProgramme($yearA, $familyA, 'DAW');
        $teacher  = $this->makeTeacher('coord.centreA');
        $this->persist($centreA, $centreB, $yearA, $familyA, $progA, $teacher);
        $progA->addCoordinator($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $centreB, [StayVoter::CREATE])
        );
    }

    // ── VIEW ─────────────────────────────────────────────────────────────────

    public function testViewGrantedToGlobalAdmin(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000019');
        $admin = $this->makeTeacher('view.admin', admin: true);
        $this->persist($centre, $year, $family, $programme, $stay, $admin);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($admin), $stay, [StayVoter::VIEW])
        );
    }

    public function testViewGrantedToCentreAdmin(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000020');
        $teacher = $this->makeTeacher('view.cadmin');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher);
        $centre->addAdmin($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW])
        );
    }

    public function testViewGrantedToCoordinator(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000021');
        $teacher = $this->makeTeacher('view.coord');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher);
        $programme->addCoordinator($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW])
        );
    }

    public function testViewGrantedToFamilyHead(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000022');
        $teacher = $this->makeTeacher('view.fhead');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher);
        $family->setHead($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW])
        );
    }

    public function testViewGrantedToGroupTutor(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000023');
        $teacher      = $this->makeTeacher('view.tutor');
        $programmeYear = $this->makeProgrammeYear($programme);
        $group        = $this->makeGroup($programmeYear);
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $programmeYear, $group);
        $group->setTutor($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW])
        );
    }

    public function testViewGrantedToGroupTeacher(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000024');
        $teacher      = $this->makeTeacher('view.teacher');
        $programmeYear = $this->makeProgrammeYear($programme);
        $group        = $this->makeGroup($programmeYear);
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $programmeYear, $group);
        $group->addTeacher($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW])
        );
    }

    public function testViewGrantedToLiaisonWithPositionInStay(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000025');
        $teacher    = $this->makeTeacher('view.liaison');
        $company    = $this->makeCompany($centre, 'Empresa View S.L.');
        $workcenter = $this->makeWorkcenter($company, 'Sede View');
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $company, $workcenter, $position);
        $company->addLiaison($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW])
        );
    }

    public function testViewDeniedToLiaisonWithNoPositionInStay(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000028');
        $teacher = $this->makeTeacher('view.liaison.nopos');
        $company = $this->makeCompany($centre, 'Empresa Sin Puestos S.L.');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $company);
        $company->addLiaison($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW])
        );
    }

    public function testViewDeniedToUnrelatedTeacher(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000026');
        $teacher = $this->makeTeacher('view.unrelated');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW])
        );
    }

    // ── ADD_POSITION ─────────────────────────────────────────────────────────

    public function testAddPositionGrantedToLiaison(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000033');
        $teacher = $this->makeTeacher('ap.liaison.yes');
        $company = $this->makeCompany($centre, 'Empresa AP S.L.');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $company);
        $company->addLiaison($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::ADD_POSITION])
        );
    }

    public function testAddPositionDeniedToNonLiaison(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000034');
        $teacher = $this->makeTeacher('ap.nonliaison');
        $company = $this->makeCompany($centre, 'Empresa AP No S.L.');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $company);
        // teacher is NOT added as liaison
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::ADD_POSITION])
        );
    }

    public function testAddPositionGrantedToCoordinator(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000035');
        $teacher = $this->makeTeacher('ap.coord');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher);
        $programme->addCoordinator($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::ADD_POSITION])
        );
    }

    // ── MANAGE_POSITION ──────────────────────────────────────────────────────

    public function testManagePositionGrantedToLiaisonOfPositionCompany(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000029');
        $teacher    = $this->makeTeacher('mp.liaison.yes');
        $company    = $this->makeCompany($centre, 'Empresa MP S.L.');
        $workcenter = $this->makeWorkcenter($company, 'Sede MP');
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $company, $workcenter, $position);
        $company->addLiaison($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $position, [StayVoter::MANAGE_POSITION])
        );
    }

    public function testManagePositionDeniedToLiaisonOfDifferentCompany(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000030');
        $teacher    = $this->makeTeacher('mp.liaison.no');
        $companyA   = $this->makeCompany($centre, 'Empresa A S.L.');
        $companyB   = $this->makeCompany($centre, 'Empresa B S.L.');
        $workcenter = $this->makeWorkcenter($companyA, 'Sede A');
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $companyA, $companyB, $workcenter, $position);
        $companyB->addLiaison($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $position, [StayVoter::MANAGE_POSITION])
        );
    }

    public function testManagePositionGrantedToCentreAdmin(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000031');
        $teacher    = $this->makeTeacher('mp.cadmin');
        $company    = $this->makeCompany($centre, 'Empresa CA S.L.');
        $workcenter = $this->makeWorkcenter($company, 'Sede CA');
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $company, $workcenter, $position);
        $centre->addAdmin($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $position, [StayVoter::MANAGE_POSITION])
        );
    }

    public function testManagePositionDeniedToUnrelatedTeacher(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000032');
        $teacher    = $this->makeTeacher('mp.unrelated');
        $company    = $this->makeCompany($centre, 'Empresa UR S.L.');
        $workcenter = $this->makeWorkcenter($company, 'Sede UR');
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $company, $workcenter, $position);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $position, [StayVoter::MANAGE_POSITION])
        );
    }

    public function testManagePositionDeniedToLiaisonWhenStudentAssigned(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000036');
        $teacher    = $this->makeTeacher('mp.liaison.student');
        $company    = $this->makeCompany($centre, 'Empresa MS S.L.');
        $workcenter = $this->makeWorkcenter($company, 'Sede MS');
        $position   = $this->makePosition($stay, $workcenter);
        $student    = (new \App\Entity\Student(new \App\Entity\PersonName('Ana', 'López')))->setStudentId('2024-001');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $company, $workcenter, $position, $student);
        $company->addLiaison($teacher);
        $position->setStudent($student);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $position, [StayVoter::MANAGE_POSITION])
        );
    }

    public function testManagePositionGrantedToCoordinatorEvenWhenStudentAssigned(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000037');
        $teacher    = $this->makeTeacher('mp.coord.student');
        $company    = $this->makeCompany($centre, 'Empresa CS S.L.');
        $workcenter = $this->makeWorkcenter($company, 'Sede CS');
        $position   = $this->makePosition($stay, $workcenter);
        $student    = (new \App\Entity\Student(new \App\Entity\PersonName('Ana', 'López')))->setStudentId('2024-002');
        $this->persist($centre, $year, $family, $programme, $stay, $teacher, $company, $workcenter, $position, $student);
        $programme->addCoordinator($teacher);
        $position->setStudent($student);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $position, [StayVoter::MANAGE_POSITION])
        );
    }

    public function testViewDeniedToTeacherInDifferentProgramme(): void
    {
        [$centre, $year, $programme, $stay, $family] = $this->makeStayContext('41000027');
        $family2       = $this->makeFamily($year, 'Sanidad');
        $programme2    = $this->makeProgramme($year, $family2, 'SMR');
        $programmeYear = $this->makeProgrammeYear($programme2);
        $group         = $this->makeGroup($programmeYear);
        $teacher       = $this->makeTeacher('view.other.prog');
        $this->persist($centre, $year, $family, $programme, $stay, $family2, $programme2, $programmeYear, $group, $teacher);
        $group->addTeacher($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW])
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array{0: EducationalCentre, 1: AcademicYear, 2: Programme, 3: Stay, 4: ProfessionalFamily} */
    private function makeStayContext(string $centreCode): array
    {
        $centre    = $this->makeCentre($centreCode);
        $year      = $this->makeYear($centre);
        $family    = $this->makeFamily($year, 'Informática');
        $programme = $this->makeProgramme($year, $family, 'DAW');
        $stay      = $this->makeStay($year, $programme);

        return [$centre, $year, $programme, $stay, $family];
    }

    private function makeCentre(string $code): EducationalCentre
    {
        return (new EducationalCentre())->setCode($code)->setName('IES ' . $code)->setCity('Sevilla');
    }

    private function makeYear(EducationalCentre $centre): AcademicYear
    {
        return (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
    }

    private function makeFamily(AcademicYear $year, string $name): ProfessionalFamily
    {
        return (new ProfessionalFamily())->setName($name)->setAcademicYear($year);
    }

    private function makeProgramme(AcademicYear $year, ProfessionalFamily $family, string $name): Programme
    {
        return (new Programme())->setName($name)->setAcademicYear($year)->setProfessionalFamily($family);
    }

    private function makeStay(AcademicYear $year, Programme $programme): Stay
    {
        $stay = new Stay();
        $stay->setName('Estancia DAW')
             ->setAcademicYear($year)
             ->setProgramme($programme)
             ->setStartDate(new \DateTimeImmutable('2025-03-01'))
             ->setEndDate(new \DateTimeImmutable('2025-06-30'));

        return $stay;
    }

    private function makeCompany(EducationalCentre $centre, string $name): Company
    {
        return (new Company())
            ->setName($name)
            ->setVatNumber('B' . substr(md5($name), 0, 8))
            ->setCity('Sevilla')
            ->setEducationalCentre($centre);
    }

    private function makeTeacher(string $username, bool $admin = false): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))
            ->setUsername($username)
            ->setAdmin($admin);
    }

    private function makeProgrammeYear(Programme $programme, string $name = '1º'): ProgrammeYear
    {
        return (new ProgrammeYear())->setName($name)->setProgramme($programme);
    }

    private function makeGroup(ProgrammeYear $programmeYear, string $name = 'A'): Group
    {
        return (new Group())->setName($name)->setProgrammeYear($programmeYear);
    }

    private function makeWorkcenter(Company $company, string $name): Workcenter
    {
        return (new Workcenter())->setName($name)->setCity('Sevilla')->setCompany($company);
    }

    private function makePosition(Stay $stay, Workcenter $workcenter): TrainingPosition
    {
        return (new TrainingPosition())
            ->setStay($stay)
            ->setWorkcenter($workcenter)
            ->setStartDate(new \DateTimeImmutable('2025-03-01'))
            ->setEndDate(new \DateTimeImmutable('2025-06-30'));
    }

    private function token(Teacher $teacher): TokenInterface
    {
        $stub = $this->createStub(TokenInterface::class);
        $stub->method('getUser')->willReturn($teacher);

        return $stub;
    }
}
