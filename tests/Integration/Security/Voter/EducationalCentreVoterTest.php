<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security\Voter;

use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Security\Voter\EducationalCentreVoter;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class EducationalCentreVoterTest extends RepositoryTestCase
{
    private EducationalCentreVoter $voter;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var EducationalCentreVoter $voter */
        $voter       = self::getContainer()->get(EducationalCentreVoter::class);
        $this->voter = $voter;
    }

    // ── supports() ──────────────────────────────────────────────────────────

    public function testAbstainsOnUnknownAttribute(): void
    {
        $teacher = $this->makeTeacher('t1');
        $centre  = $this->makeCentre('41100001');
        $this->persist($centre, $teacher);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->token($teacher), $centre, ['unknown.attr'])
        );
    }

    public function testAbstainsWhenSubjectIsNotEducationalCentre(): void
    {
        $teacher = $this->makeTeacher('t2');
        $this->persist($teacher);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->token($teacher), new \stdClass(), [EducationalCentreVoter::SECTION])
        );
    }

    public function testAbstainsWhenSubjectIsNull(): void
    {
        $teacher = $this->makeTeacher('t2null');
        $this->persist($teacher);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->token($teacher), null, [EducationalCentreVoter::SECTION])
        );
    }

    // ── Administrador global ─────────────────────────────────────────────────

    public function testGlobalAdminIsGrantedSection(): void
    {
        $admin  = $this->makeTeacher('admin1', true);
        $centre = $this->makeCentre('41100002');
        $this->persist($centre, $admin);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($admin), $centre, [EducationalCentreVoter::SECTION])
        );
    }

    // ── Equipo directivo ─────────────────────────────────────────────────────

    public function testEquipoDirectivoMemberIsGrantedSection(): void
    {
        $teacher = $this->makeTeacher('directivo1');
        $centre  = $this->makeCentre('41100003');
        $this->persist($centre, $teacher);

        $centre->addAdmin($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $centre, [EducationalCentreVoter::SECTION])
        );
    }

    public function testEquipoDirectivoOfDifferentCentreIsDenied(): void
    {
        $teacher = $this->makeTeacher('directivo2');
        $centreA = $this->makeCentre('41100004');
        $centreB = $this->makeCentre('41100005');
        $this->persist($centreA, $centreB, $teacher);

        $centreA->addAdmin($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $centreB, [EducationalCentreVoter::SECTION])
        );
    }

    // ── Docente sin privilegios ──────────────────────────────────────────────

    public function testUnrelatedTeacherIsDenied(): void
    {
        $teacher = $this->makeTeacher('t3');
        $centre  = $this->makeCentre('41100006');
        $this->persist($centre, $teacher);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $centre, [EducationalCentreVoter::SECTION])
        );
    }

    // ── Usuario anónimo ──────────────────────────────────────────────────────

    public function testAnonymousUserIsDenied(): void
    {
        $centre = $this->makeCentre('41100007');
        $this->persist($centre);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->anonymousToken(), $centre, [EducationalCentreVoter::SECTION])
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeTeacher(string $username, bool $admin = false): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))
            ->setUsername($username)
            ->setAdmin($admin);
    }

    private function makeCentre(string $code): EducationalCentre
    {
        return (new EducationalCentre())
            ->setCode($code)
            ->setName('IES ' . $code)
            ->setCity('Sevilla');
    }

    private function token(Teacher $teacher): TokenInterface
    {
        $stub = $this->createStub(TokenInterface::class);
        $stub->method('getUser')->willReturn($teacher);
        return $stub;
    }

    private function anonymousToken(): TokenInterface
    {
        $stub = $this->createStub(TokenInterface::class);
        $stub->method('getUser')->willReturn(null);
        return $stub;
    }
}
