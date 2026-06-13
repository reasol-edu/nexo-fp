<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Repository\EducationalCentreRepository;
use App\Repository\StayRepository;
use App\Repository\TrainingPositionRepository;
use App\Service\PendingTasksProvider;
use App\Service\TenantContext;
use App\Tests\Integration\RepositoryTestCase;
use App\Twig\Components\NotificationBellComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\User\UserInterface;

class NotificationBellComponentTest extends RepositoryTestCase
{
    public function testNoUserReturnsEmpty(): void
    {
        $centre = $this->makeCentre('43000001');
        $this->makeFreePositionStay($centre, 'FFEOE Libre');

        $bell = $this->makeBell($centre, null);

        self::assertSame(0, $bell->getTotal());
        self::assertSame([], $bell->getVisibleItems());
    }

    public function testNoSelectedCentreReturnsEmpty(): void
    {
        $centre = $this->makeCentre('43000002');
        $this->makeFreePositionStay($centre, 'FFEOE Libre');
        $admin = $this->makeAdmin('admin.bell.2');

        $bell = $this->makeBell(null, $admin);

        self::assertSame(0, $bell->getTotal());
    }

    public function testReturnsItemsForCurrentTeacher(): void
    {
        $centre = $this->makeCentre('43000003');
        $this->makeFreePositionStay($centre, 'FFEOE Libre');
        $admin = $this->makeAdmin('admin.bell.3');

        $bell = $this->makeBell($centre, $admin);

        self::assertGreaterThanOrEqual(1, $bell->getTotal());
        self::assertContains('free_positions', array_column($bell->getVisibleItems(), 'type'));
    }

    public function testLimitsVisibleItemsToMaxItems(): void
    {
        $centre = $this->makeCentre('43000004');
        for ($i = 0; $i < NotificationBellComponent::MAX_ITEMS + 3; ++$i) {
            $this->makeFreePositionStay($centre, 'FFEOE Libre ' . $i);
        }
        $admin = $this->makeAdmin('admin.bell.4');

        $bell = $this->makeBell($centre, $admin);

        self::assertSame(NotificationBellComponent::MAX_ITEMS + 3, $bell->getTotal());
        self::assertCount(NotificationBellComponent::MAX_ITEMS, $bell->getVisibleItems());
    }

    public function testIsolatedBetweenCentres(): void
    {
        $centreA = $this->makeCentre('43000005');
        $centreB = $this->makeCentre('43000006');
        $this->makeFreePositionStay($centreB, 'FFEOE Otro Centro');
        $adminA = $this->makeAdmin('admin.bell.a');

        // Admin A views centre A; the alert lives in centre B's year.
        $bell = $this->makeBell($centreA, $adminA);

        self::assertSame(0, $bell->getTotal());
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makeBell(?EducationalCentre $selectedCentre, ?UserInterface $user): NotificationBellComponent
    {
        $session = new Session(new MockArraySessionStorage());
        if ($selectedCentre !== null) {
            $session->set('tenant.centre_id', $selectedCentre->getId()->toRfc4122());
        }
        $request = new Request();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        /** @var EducationalCentreRepository $centres */
        $centres = self::getContainer()->get(EducationalCentreRepository::class);
        $tenant  = new TenantContext($stack, $centres, $this->em);

        /** @var StayRepository $stays */
        $stays = self::getContainer()->get(StayRepository::class);
        /** @var TrainingPositionRepository $positions */
        $positions = self::getContainer()->get(TrainingPositionRepository::class);
        /** @var \Symfony\Component\Clock\ClockInterface $clock */
        $clock    = self::getContainer()->get('clock');
        $provider = new PendingTasksProvider($stays, $positions, $clock);

        return new class($tenant, $provider, $user) extends NotificationBellComponent {
            public function __construct(
                TenantContext $tenant,
                PendingTasksProvider $provider,
                private readonly ?UserInterface $stubUser,
            ) {
                parent::__construct($tenant, $provider);
            }

            protected function getUser(): ?UserInterface
            {
                return $this->stubUser;
            }
        };
    }

    private function makeCentre(string $code): EducationalCentre
    {
        $centre = (new EducationalCentre())->setCode($code)->setName('IES ' . $code)->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);
        $this->persist($centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();

        return $centre;
    }

    private function makeAdmin(string $username): Teacher
    {
        $admin = (new Teacher(new PersonName('Admin', 'Bell')))->setUsername($username)->setAdmin(true);
        $this->persist($admin);

        return $admin;
    }

    private function makeFreePositionStay(EducationalCentre $centre, string $name): void
    {
        $year   = $centre->requireActiveAcademicYear();
        $family = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $prog   = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($family);
        $stay   = (new Stay())
            ->setName($name)
            ->setAcademicYear($year)
            ->setProgramme($prog)
            ->setStartDate(new \DateTimeImmutable('-10 days'))
            ->setEndDate(new \DateTimeImmutable('+20 days'));
        $position = (new TrainingPosition())->setStay($stay);
        $this->persist($family, $prog, $stay, $position);
    }
}
