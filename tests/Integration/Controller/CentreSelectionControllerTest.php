<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class CentreSelectionControllerTest extends ControllerTestCase
{
    // ── listado ───────────────────────────────────────────────────────────────

    public function testCentreListRedirectsAnonymousUser(): void
    {
        $this->client->request('GET', '/centro');

        self::assertResponseRedirects();
    }

    public function testCentreListIsAccessibleToAuthenticatedTeacher(): void
    {
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $this->client->request('GET', '/centro');

        self::assertResponseIsSuccessful();
    }

    // ── selección ─────────────────────────────────────────────────────────────

    public function testChooseCentreSelectsCentreAndRedirectsToDashboard(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($centre, $teacher);
        $centre->addAdmin($teacher);
        $this->flush();

        $this->loginAs($teacher);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/centro');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/centro/' . $centreId, ['_token' => $token]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testChooseCentreWithInvalidCsrfTokenIsDenied(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($centre, $teacher);
        $centre->addAdmin($teacher);
        $this->flush();

        $this->loginAs($teacher);

        $centreId = $centre->getId()->toRfc4122();
        $this->client->request('POST', '/centro/' . $centreId, ['_token' => 'token-invalido']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testChooseCentreOfAnotherTeacherIsDenied(): void
    {
        $centreA  = $this->makeCentre('41000001');
        $centreB  = $this->makeCentre('41000002');
        $teacherA = $this->makeTeacher('teacher.a');
        $teacherB = $this->makeTeacher('teacher.b');
        $this->persist($centreA, $centreB, $teacherA, $teacherB);
        $centreA->addAdmin($teacherA);
        $centreB->addAdmin($teacherB);
        $this->flush();

        // teacherA is logged in; its visible form token is for centreA.
        // Posting that token to centreB's URL fails CSRF (different token ID).
        $this->loginAs($teacherA);

        $centreAId = $centreA->getId()->toRfc4122();
        $centreBId = $centreB->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/centro');
        // The token on the page is for 'select_centre_{centreAId}'
        $token = $crawler->filter('[name="_token"]')->first()->attr('value');

        // Using centreA's token against centreB → CSRF mismatch → 403
        $this->client->request('POST', '/centro/' . $centreBId, ['_token' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
    }

    private function makeCentre(string $code): EducationalCentre
    {
        return (new EducationalCentre())->setCode($code)->setName('IES ' . $code)->setCity('Sevilla');
    }
}
