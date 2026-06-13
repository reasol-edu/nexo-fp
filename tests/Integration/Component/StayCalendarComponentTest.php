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
use App\Service\TenantContext;
use App\Tests\Integration\RepositoryTestCase;
use App\Twig\Components\StayCalendarComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class StayCalendarComponentTest extends RepositoryTestCase
{
    // ── mount: clamping de año y mes ───────────────────────────────────────────

    public function testMountDefaultsInvalidYearAndMonthToToday(): void
    {
        $today     = new \DateTimeImmutable();
        $component  = $this->makeComponent(null, null);
        $component->year  = 0;
        $component->month = 0;
        $component->mount();

        self::assertSame((int) $today->format('Y'), $component->year);
        self::assertSame((int) $today->format('n'), $component->month);
    }

    public function testMountRejectsOutOfRangeYear(): void
    {
        $today     = new \DateTimeImmutable();
        $component = $this->makeComponent(null, null);
        $component->year  = 1999;
        $component->month = 5;
        $component->mount();

        self::assertSame((int) $today->format('Y'), $component->year);
        self::assertSame(5, $component->month);
    }

    public function testMountKeepsValidYearAndMonth(): void
    {
        $component = $this->makeComponent(null, null);
        $component->year  = 2042;
        $component->month = 7;
        $component->mount();

        self::assertSame(2042, $component->year);
        self::assertSame(7, $component->month);
    }

    // ── navegación ─────────────────────────────────────────────────────────────

    public function testPreviousMonthWrapsYear(): void
    {
        $component = $this->makeComponent(null, null);
        $component->year  = 2026;
        $component->month = 1;
        $component->previousMonth();

        self::assertSame(2025, $component->year);
        self::assertSame(12, $component->month);
    }

    public function testNextMonthWrapsYear(): void
    {
        $component = $this->makeComponent(null, null);
        $component->year  = 2026;
        $component->month = 12;
        $component->nextMonth();

        self::assertSame(2027, $component->year);
        self::assertSame(1, $component->month);
    }

    public function testGoTodayResetsToCurrentMonth(): void
    {
        $today     = new \DateTimeImmutable();
        $component = $this->makeComponent(null, null);
        $component->year  = 2000;
        $component->month = 1;
        $component->goToday();

        self::assertSame((int) $today->format('Y'), $component->year);
        self::assertSame((int) $today->format('n'), $component->month);
    }

    // ── getWeeks ────────────────────────────────────────────────────────────────

    public function testWeeksEmptyWithoutSelectedCentre(): void
    {
        $component = $this->makeComponent(null, null);
        $component->year  = 2026;
        $component->month = 3;

        self::assertSame([], $component->getWeeks());
    }

    public function testWeeksContainStayInMonth(): void
    {
        $centre = $this->makeCentre('44000001');
        $this->makeStayWithPositions($centre, 'FFEOE Marzo', '2026-03-10', '2026-03-20', signed: 1, unsigned: 1);

        $component = $this->makeComponent($centre, null);
        $component->year  = 2026;
        $component->month = 3;

        $segments = $this->allSegments($component);
        self::assertNotEmpty($segments);
        foreach ($segments as $segment) {
            self::assertSame('FFEOE Marzo', $segment['stay']->getName());
        }
    }

    public function testWeeksCountUnsignedPositionsOnEndSegment(): void
    {
        $centre = $this->makeCentre('44000002');
        $this->makeStayWithPositions($centre, 'FFEOE Firmas', '2026-03-05', '2026-03-15', signed: 1, unsigned: 2);

        $component = $this->makeComponent($centre, null);
        $component->year  = 2026;
        $component->month = 3;

        $endSegments = array_filter($this->allSegments($component), static fn (array $s): bool => $s['isEnd']);
        self::assertCount(1, $endSegments);
        self::assertSame(2, array_values($endSegments)[0]['unsignedCount']);
    }

    public function testWeeksAssignLanesForOverlappingStays(): void
    {
        $centre = $this->makeCentre('44000003');
        $this->makeStayWithPositions($centre, 'FFEOE A', '2026-03-02', '2026-03-25');
        $this->makeStayWithPositions($centre, 'FFEOE B', '2026-03-03', '2026-03-26');

        $component = $this->makeComponent($centre, null);
        $component->year  = 2026;
        $component->month = 3;

        $maxLane = 0;
        foreach ($component->getWeeks() as $week) {
            $maxLane = max($maxLane, $week['maxLane']);
        }
        self::assertGreaterThanOrEqual(1, $maxLane);
    }

    public function testWeeksFilteredByViewerPermissions(): void
    {
        $centre = $this->makeCentre('44000004');
        $this->makeStayWithPositions($centre, 'FFEOE Marzo', '2026-03-10', '2026-03-20');
        // Teacher with no role in the centre sees no stays.
        $outsider = (new Teacher(new PersonName('Out', 'Sider')))->setUsername('calendar.outsider');
        $this->persist($outsider);

        $component = $this->makeComponent($centre, $outsider);
        $component->year  = 2026;
        $component->month = 3;

        self::assertSame([], $this->allSegments($component));
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function allSegments(StayCalendarComponent $component): array
    {
        $segments = [];
        foreach ($component->getWeeks() as $week) {
            foreach ($week['segments'] as $segment) {
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    private function makeComponent(?EducationalCentre $selectedCentre, ?UserInterface $user): StayCalendarComponent
    {
        // Detach in-memory entities so the calendar query hydrates fresh managed
        // instances with populated (non-stale) training-position collections.
        $this->em->clear();

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
        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get(TranslatorInterface::class);

        return new class($stays, $tenant, $translator, $user) extends StayCalendarComponent {
            public function __construct(
                StayRepository $stays,
                TenantContext $tenant,
                TranslatorInterface $translator,
                private readonly ?UserInterface $stubUser,
            ) {
                parent::__construct($stays, $tenant, $translator);
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

    private function makeStayWithPositions(
        EducationalCentre $centre,
        string $name,
        string $start,
        string $end,
        int $signed = 0,
        int $unsigned = 0,
    ): Stay {
        $year   = $centre->requireActiveAcademicYear();
        $family = (new ProfessionalFamily())->setName('Fam ' . $name)->setAcademicYear($year);
        $prog   = (new Programme())->setName('Prog ' . $name)->setAcademicYear($year)->setProfessionalFamily($family);
        $stay   = (new Stay())
            ->setName($name)
            ->setAcademicYear($year)
            ->setProgramme($prog)
            ->setStartDate(new \DateTimeImmutable($start))
            ->setEndDate(new \DateTimeImmutable($end));
        $this->persist($family, $prog, $stay);

        for ($i = 0; $i < $signed; ++$i) {
            $this->persist((new TrainingPosition())->setStay($stay)->setSigned(true));
        }
        for ($i = 0; $i < $unsigned; ++$i) {
            $this->persist((new TrainingPosition())->setStay($stay)->setSigned(false));
        }

        return $stay;
    }
}
