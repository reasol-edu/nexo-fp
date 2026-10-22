<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\PersonName;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Teacher;
use PHPUnit\Framework\TestCase;

/** La coordinación dual vive en el nivel; la enseñanza ofrece el atajo «todos sus niveles». */
class ProgrammeCoordinatorsTest extends TestCase
{
    public function testACoordinatorAssignedToTheProgrammeCoordinatesEveryLevel(): void
    {
        [$programme, $first, $second] = $this->programmeWithTwoLevels();
        $ana = $this->teacher('ana');

        $programme->addCoordinator($ana);

        self::assertTrue($first->getCoordinators()->contains($ana));
        self::assertTrue($second->getCoordinators()->contains($ana));
        self::assertSame([$ana], $programme->getCoordinatorsOfAllLevels());
        self::assertSame([$ana], $programme->getCoordinatorsOfAnyLevel());
    }

    public function testACoordinatorOfASingleLevelIsOnlyInTheAnyLevelList(): void
    {
        [$programme, $first, $second] = $this->programmeWithTwoLevels();
        $ana  = $this->teacher('ana');
        $beto = $this->teacher('beto');
        $programme->addCoordinator($ana);
        $second->addCoordinator($beto);

        self::assertSame([$ana], $programme->getCoordinatorsOfAllLevels(), 'Beto solo coordina 2.º: no es de «toda la enseñanza»');
        self::assertEqualsCanonicalizing([$ana, $beto], $programme->getCoordinatorsOfAnyLevel());
        self::assertFalse($first->getCoordinators()->contains($beto));
    }

    public function testRemovingFromTheProgrammeTakesTheCoordinatorOutOfEveryLevel(): void
    {
        [$programme, , $second] = $this->programmeWithTwoLevels();
        $ana = $this->teacher('ana');
        $programme->addCoordinator($ana);

        $programme->removeCoordinator($ana);

        self::assertSame([], $programme->getCoordinatorsOfAnyLevel());
        self::assertCount(0, $second->getCoordinators());
    }

    public function testAProgrammeWithoutLevelsCannotHoldCoordinators(): void
    {
        $programme = (new Programme())->setName('DAW');

        $this->expectException(\LogicException::class);
        $programme->addCoordinator($this->teacher('ana'));
    }

    public function testAProgrammeWithoutLevelsHasNoCoordinators(): void
    {
        $programme = (new Programme())->setName('DAW');

        self::assertSame([], $programme->getCoordinatorsOfAllLevels());
        self::assertSame([], $programme->getCoordinatorsOfAnyLevel());
    }

    public function testCreatingALevelRegistersItInTheProgramme(): void
    {
        $programme = (new Programme())->setName('DAW');
        $level     = (new ProgrammeYear())->setName('1.º')->setProgramme($programme);

        self::assertTrue($programme->getProgrammeYears()->contains($level));
    }

    /** @return array{Programme, ProgrammeYear, ProgrammeYear} */
    private function programmeWithTwoLevels(): array
    {
        $programme = (new Programme())->setName('DAW');
        $first     = (new ProgrammeYear())->setName('1.º DAW')->setProgramme($programme);
        $second    = (new ProgrammeYear())->setName('2.º DAW')->setProgramme($programme);

        return [$programme, $first, $second];
    }

    private function teacher(string $username): Teacher
    {
        $teacher = (new Teacher(new PersonName('Docente', $username)))->setUsername($username);
        (new \ReflectionProperty(Teacher::class, 'id'))->setValue($teacher, \Symfony\Component\Uid\Uuid::v4());

        return $teacher;
    }
}
