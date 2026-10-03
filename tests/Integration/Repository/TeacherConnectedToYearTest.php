<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

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
use App\Repository\TeacherRepository;
use App\Tests\Integration\RepositoryTestCase;

/** Vinculaciones de un docente con un curso académico (base para retirar docentes sin actividad). */
class TeacherConnectedToYearTest extends RepositoryTestCase
{
    public function testEveryKindOfLinkCountsAndOtherYearsDoNot(): void
    {
        $centre = (new EducationalCentre())->setCode('41000003')->setName('IES')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);
        $old    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);

        $names = ['tutor', 'teacher', 'coord', 'head', 'cadmin', 'liaison', 'pos', 'free', 'oldyear'];
        $t = [];
        foreach ($names as $n) {
            $t[$n] = (new Teacher(new PersonName($n, 'X')))->setUsername($n);
        }

        $family = (new ProfessionalFamily())->setName('Inf')->setAcademicYear($year)->setHead($t['head']);
        $prog   = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($family)->addCoordinator($t['coord']);
        $level  = (new ProgrammeYear())->setName('1')->setProgramme($prog);
        $group  = (new Group())->setName('G')->setProgrammeYear($level)->addTutor($t['tutor'])->addTeacher($t['teacher']);

        // Vinculado solo en un curso anterior: no cuenta para el actual
        $oldFamily = (new ProfessionalFamily())->setName('Inf')->setAcademicYear($old)->setHead($t['oldyear']);

        $company = (new Company())->setName('E')->setVatNumber('B1')->setCity('Sevilla')->setEducationalCentre($centre)->addLiaison($t['liaison']);
        $stay    = (new Stay())->setName('S')->setAcademicYear($year)->addProgramme($prog)
            ->setStartDate(new \DateTimeImmutable('2026-03-01'))->setEndDate(new \DateTimeImmutable('2026-06-01'));
        $position = (new TrainingPosition())->setStay($stay)->setAcademicTutor($t['pos']);
        $centre->addAdmin($t['cadmin']);

        $this->persist($centre, $year, $old, ...array_values($t), ...[$family, $prog, $level, $group, $oldFamily, $company, $stay, $position]);

        /** @var TeacherRepository $repo */
        $repo = self::getContainer()->get(TeacherRepository::class);
        $connected = array_keys($repo->findConnectedIdsForYear($year));
        $usernames = array_values(array_map(static fn (string $id): string => array_search($id, array_map(static fn (Teacher $x): string => $x->getId()->toRfc4122(), $t), true), $connected));
        sort($usernames);

        self::assertSame(['cadmin', 'coord', 'head', 'liaison', 'pos', 'teacher', 'tutor'], $usernames);
        self::assertNotContains('free', $usernames);
        self::assertNotContains('oldyear', $usernames);
    }
}
