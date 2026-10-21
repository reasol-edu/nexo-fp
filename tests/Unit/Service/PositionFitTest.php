<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\PersonName;
use App\Entity\Student;
use App\Entity\StudentYearProfile;
use App\Entity\TrainingPosition;
use App\Entity\Workcenter;
use App\Service\PositionFit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/** Orden de los puestos al asignar según la ficha del estudiante (empresa aportada, localidades). */
class PositionFitTest extends TestCase
{
    public function testNoProfileOrNoWorkcenterNeverFits(): void
    {
        self::assertNull(PositionFit::of($this->position('Alfa S.L.', 'Sevilla'), null));
        self::assertNull(PositionFit::of(new TrainingPosition(), $this->profile()->setResidenceCity('Sevilla')));
    }

    public function testBroughtCompanyBeatsPreferredCityAndResidence(): void
    {
        $profile = $this->profile()->setBringsCompany(true)->setBroughtCompanyName('talleres hermanos ledesma')
            ->setPreferredCity('Linares')->setResidenceCity('Linares');

        self::assertSame(PositionFit::COMPANY, PositionFit::of($this->position('Talleres Hermanos Ledesma S.L.', 'Linares'), $profile), 'el texto libre está contenido en el nombre');
        self::assertSame(PositionFit::PREFERRED, PositionFit::of($this->position('Otra S.L.', 'Linares'), $profile));
    }

    public function testCitiesMatchIgnoringCaseAccentsAndSpaces(): void
    {
        $profile = $this->profile()->setPreferredCity('  ÚBEDA ')->setResidenceCity('Alcalá de Guadaíra');

        self::assertSame(PositionFit::PREFERRED, PositionFit::of($this->position('A', 'Úbeda'), $profile));
        self::assertSame(PositionFit::PREFERRED, PositionFit::of($this->position('A', 'ubeda'), $profile));
        self::assertSame(PositionFit::RESIDENCE, PositionFit::of($this->position('A', 'alcala de guadaira'), $profile));
        self::assertNull(PositionFit::of($this->position('A', 'Jaén'), $profile));
    }

    public function testVeryShortBroughtCompanyDoesNotMatchEverything(): void
    {
        $profile = $this->profile()->setBringsCompany(true)->setBroughtCompanyName('S');

        self::assertNull(PositionFit::of($this->position('Sistemas S.L.', 'Jaén'), $profile));
    }

    public function testRankPutsFitsFirstKeepingTheOriginalOrderWithinEachLevel(): void
    {
        $profile = $this->profile()->setBringsCompany(true)->setBroughtCompanyName('Beta')->setPreferredCity('Linares')->setResidenceCity('Lebrija');
        $none1 = $this->position('Gamma', 'Jaén');
        $res   = $this->position('Delta', 'Lebrija');
        $pref1 = $this->position('Epsilon', 'Linares');
        $comp  = $this->position('Beta S.L.', 'Jaén');
        $none2 = $this->position('Zeta', 'Úbeda');
        $pref2 = $this->position('Eta', 'Linares');

        [$ordered, $fits] = PositionFit::rank([$none1, $res, $pref1, $comp, $none2, $pref2], $profile);

        self::assertSame([$comp, $pref1, $pref2, $res, $none1, $none2], $ordered);
        self::assertSame(PositionFit::COMPANY, $fits[$comp->getId()->toRfc4122()]);
        self::assertSame(PositionFit::RESIDENCE, $fits[$res->getId()->toRfc4122()]);
        self::assertArrayNotHasKey($none1->getId()->toRfc4122(), $fits);
    }

    private function profile(): StudentYearProfile
    {
        return new StudentYearProfile(new Student(new PersonName('Ana', 'Pérez')), new AcademicYear());
    }

    private function position(string $company, string $city): TrainingPosition
    {
        $companyEntity = (new Company())->setName($company)->setCity($city);
        $workcenter    = (new Workcenter())->setName('Sede')->setCity($city)->setCompany($companyEntity);

        $position = (new TrainingPosition())->setWorkcenter($workcenter);
        // El id lo asigna Doctrine al persistir; aquí no hay base de datos.
        (new \ReflectionProperty(TrainingPosition::class, 'id'))->setValue($position, Uuid::v7());

        return $position;
    }
}
