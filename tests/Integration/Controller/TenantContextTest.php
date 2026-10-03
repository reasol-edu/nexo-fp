<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

/**
 * El centro guardado en la sesión se valida en cada petición.
 */
class TenantContextTest extends ControllerTestCase
{
    public function testTeacherWhoLosesAccessToTheCentreMidSessionGoesBackToTheCentrePicker(): void
    {
        [$teacher, $centre] = $this->setUpTeacherInCentre();
        $this->loginAs($teacher, $centre, ensureCentreAccess: false);

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        // Se le retira el acceso al centro con la sesión abierta.
        $this->em->getConnection()->executeStatement('DELETE FROM group_teacher');
        $this->em->clear();

        $this->client->request('GET', '/');
        self::assertResponseRedirects('/centro');

        // Y no conserva el centro en la sesión: las peticiones siguientes tampoco lo ven.
        $this->client->request('GET', '/empresas');
        self::assertResponseRedirects('/centro');
    }

    public function testMalformedCentreIdInSessionIsTreatedAsNoCentre(): void
    {
        [$teacher, $centre] = $this->setUpTeacherInCentre();
        $this->loginAs($teacher, $centre, ensureCentreAccess: false);

        $session = $this->client->getRequest()->getSession();
        $session->set('tenant.centre_id', 'esto-no-es-un-uuid');
        $session->save();

        $this->client->request('GET', '/');

        // Antes: error 500 al convertir el valor con el tipo uuid de DBAL. Ahora la sesión obsoleta
        // se descarta y, como solo tiene un centro, se le vuelve a seleccionar sin error.
        self::assertResponseIsSuccessful();
        self::assertSame($centre->getId()->toRfc4122(), $this->client->getRequest()->getSession()->get('tenant.centre_id'));
    }

    public function testMalformedYearIdInSessionFallsBackToTheActiveYear(): void
    {
        [$teacher, $centre] = $this->setUpTeacherInCentre();
        $this->loginAs($teacher, $centre, ensureCentreAccess: false);

        $session = $this->client->getRequest()->getSession();
        $session->set('tenant.year_id', 'tampoco-es-un-uuid');
        $session->save();

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    /** @return array{0: Teacher, 1: EducationalCentre, 2: Group} */
    private function setUpTeacherInCentre(): array
    {
        $teacher   = (new Teacher(new PersonName('Docente', 'Normal')))->setUsername('docente.normal');
        $centre    = (new EducationalCentre())->setCode('41000888')->setName('IES Acceso')->setCity('Sevilla');
        $year      = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);
        $family    = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $programme = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);
        $level     = (new ProgrammeYear())->setName('Primero')->setProgramme($programme);
        $group     = (new Group())->setName('DAW1')->setProgrammeYear($level);
        $group->addTeacher($teacher);

        $this->persist($teacher, $centre, $year, $family, $programme, $level, $group);
        $centre->setActiveAcademicYear($year);
        $this->flush();

        return [$teacher, $centre, $group];
    }
}
