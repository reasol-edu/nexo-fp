<?php

declare(strict_types=1);

namespace App\Tests\Integration;

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
    protected function loginAs(Teacher $teacher, ?EducationalCentre $centre = null): void
    {
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
}
