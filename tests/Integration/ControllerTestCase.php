<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\SettingDefinition;
use App\Entity\SettingType;
use App\Entity\Teacher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ControllerTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // With SQLite :memory: every kernel reboot opens a fresh connection → empty DB.
        // Disabling the reboot keeps the same kernel (and DBAL connection) across all
        // requests within a single test, so the schema created below survives.
        $this->client->disableReboot();

        /** @var EntityManagerInterface $em */
        $em       = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;

        (new SchemaTool($this->em))->createSchema(
            $this->em->getMetadataFactory()->getAllMetadata()
        );

        $this->seedDefaultSettings();
    }

    protected function tearDown(): void
    {
        (new SchemaTool($this->em))->dropSchema(
            $this->em->getMetadataFactory()->getAllMetadata()
        );

        parent::tearDown();
    }

    private function seedDefaultSettings(): void
    {
        // [key, type, default, globalScope, centreScope, teacherScope, minValue, maxValue]
        $defs = [
            ['page.size',                             SettingType::Integer, '20',   false, false, true,  5,    100],
            ['security.idle_timeout_minutes',         SettingType::Integer, '120',  true,  false, false, 0,    1440],
            ['email.log_retention_days',              SettingType::Integer, '90',   true,  false, false, 0,    3650],
            ['email.notifications',                   SettingType::Boolean, 'true', true,  true,  true,  null, null],
            ['email.notification.tutor_assigned',     SettingType::Boolean, 'true', true,  true,  true,  null, null],
            ['email.notification.positions_created',  SettingType::Boolean, 'true', true,  true,  true,  null, null],
            ['email.notification.signature_reminder', SettingType::Boolean, 'true', true,  true,  true,  null, null],
        ];

        foreach ($defs as [$key, $type, $default, $global, $centre, $teacher, $min, $max]) {
            $def = (new SettingDefinition())
                ->setKey($key)
                ->setType($type)
                ->setDefaultValue($default)
                ->setGlobalScope($global)
                ->setCentreScope($centre)
                ->setTeacherScope($teacher)
                ->setMinValue($min)
                ->setMaxValue($max);
            $this->em->persist($def);
        }

        $this->em->flush();
    }

    protected function persist(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    protected function flush(): void
    {
        $this->em->flush();
    }

    /**
     * Returns the body of a StreamedResponse. KernelBrowser already consumes
     * the stream when filtering the response, so it must be read from the
     * BrowserKit internal response instead of sending the content again.
     */
    protected function getStreamedContent(): string
    {
        return $this->client->getInternalResponse()->getContent();
    }

    /**
     * Logs in as the given teacher. Makes one request to establish the
     * session, then optionally injects the tenant centre into that session.
     */
    protected function loginAs(Teacher $teacher, ?EducationalCentre $centre = null, bool $ensureCentreAccess = true): void
    {
        // El centro de la sesión se revalida en cada petición (TenantContext): un docente sin ninguna
        // relación con el centro volvería al selector de centro. Para que los tests de «docente sin
        // permisos» sigan comprobando que deniega el voter (y no esa redirección), se le da por defecto
        // el acceso mínimo, sin privilegios: ser docente de un grupo del centro.
        if ($centre !== null && $ensureCentreAccess) {
            $this->giveUnprivilegedCentreAccess($teacher, $centre);
        }

        $this->client->loginUser($teacher);
        // One request is needed to materialise the session file before we can
        // add keys to it.  /centro is always accessible to an authenticated teacher.
        $this->client->request('GET', '/centro');

        if ($centre !== null) {
            $session = $this->client->getRequest()->getSession();
            $session->set('tenant.centre_id', $centre->getId()->toRfc4122());
            $session->save();
        }
    }

    private function giveUnprivilegedCentreAccess(Teacher $teacher, EducationalCentre $centre): void
    {
        if ($teacher->isAdmin()) {
            return;
        }

        /** @var \App\Repository\EducationalCentreRepository $centres */
        $centres = self::getContainer()->get(\App\Repository\EducationalCentreRepository::class);
        if ($centres->isAccessibleByTeacher($centre, $teacher)) {
            return;
        }

        $year      = (new AcademicYear())->setName('Acceso mínimo')->setEducationalCentre($centre);
        $family    = (new \App\Entity\ProfessionalFamily())->setName('Familia de acceso mínimo')->setAcademicYear($year);
        $programme = (new \App\Entity\Programme())->setName('Enseñanza de acceso mínimo')->setProfessionalFamily($family)->setAcademicYear($year);
        $level     = (new \App\Entity\ProgrammeYear())->setName('Nivel')->setProgramme($programme);
        $group     = (new \App\Entity\Group())->setName('Grupo de acceso mínimo')->setProgrammeYear($level);
        $group->addTeacher($teacher);

        $this->persist($year, $family, $programme, $level, $group);
    }

    /**
     * Simulates an admin switching to a past (non-active) academic year.
     * Must be called after loginAs() so the session already exists.
     */
    protected function viewPastYear(AcademicYear $year): void
    {
        $session = $this->client->getRequest()->getSession();
        $session->set('tenant.year_id', $year->getId()->toRfc4122());
        $session->save();
    }
}
