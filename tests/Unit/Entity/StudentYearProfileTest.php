<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AcademicYear;
use App\Entity\PersonName;
use App\Entity\Student;
use App\Entity\StudentYearProfile;
use PHPUnit\Framework\TestCase;

final class StudentYearProfileTest extends TestCase
{
    private function profile(): StudentYearProfile
    {
        return new StudentYearProfile(new Student(new PersonName('Ana', 'García')), new AcademicYear());
    }

    public function testStartsEmptyAndTakingTheFfeoe(): void
    {
        $profile = $this->profile();

        self::assertNull($profile->hasNuss());
        self::assertTrue($profile->takesFfeoe());
        self::assertFalse($profile->hasCar());
        self::assertFalse($profile->bringsCompany());
        self::assertNull($profile->getAttitude());
        self::assertFalse($profile->isMissingNuss(), 'sin informar no es «sin NUSS»');
    }

    public function testOnlyAConfirmedMissingNussOfSomeoneWhoDoesTheFfeoeIsFlagged(): void
    {
        $profile = $this->profile()->setHasNuss(false);
        self::assertTrue($profile->isMissingNuss());

        $profile->setTakesFfeoe(false);
        self::assertFalse($profile->isMissingNuss(), 'quien no realiza la FFEOE no necesita NUSS');

        self::assertFalse($this->profile()->setHasNuss(true)->isMissingNuss());
    }

    public function testDoingTheFfeoeAgainDropsTheExclusionReason(): void
    {
        $profile = $this->profile()->setTakesFfeoe(false)->setFfeoeExclusionReason('  Ya la realizó  ');
        self::assertSame('Ya la realizó', $profile->getFfeoeExclusionReason());

        $profile->setTakesFfeoe(true);

        self::assertNull($profile->getFfeoeExclusionReason());
    }

    public function testNoLongerBringingACompanyDropsItsName(): void
    {
        $profile = $this->profile()->setBringsCompany(true)->setBroughtCompanyName(' Alfa S.L. ');
        self::assertSame('Alfa S.L.', $profile->getBroughtCompanyName());

        $profile->setBringsCompany(false);

        self::assertNull($profile->getBroughtCompanyName());
    }

    public function testAttitudeOutsideOneToFiveIsDiscarded(): void
    {
        $profile = $this->profile();

        self::assertSame(3, $profile->setAttitude(3)->getAttitude());
        self::assertNull($profile->setAttitude(0)->getAttitude());
        self::assertNull($profile->setAttitude(6)->getAttitude());
        self::assertNull($profile->setAttitude(null)->getAttitude());
    }

    public function testBlankCitiesBecomeNull(): void
    {
        $profile = $this->profile()->setResidenceCity('  Lebrija ')->setPreferredCity('   ');

        self::assertSame('Lebrija', $profile->getResidenceCity());
        self::assertNull($profile->getPreferredCity());
    }
}
