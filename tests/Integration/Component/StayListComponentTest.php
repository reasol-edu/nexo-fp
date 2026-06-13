<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Stay;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\StayRepository;
use App\Service\AppSettings;
use App\Tests\Integration\ControllerTestCase;
use App\Twig\Components\StayListComponent;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

class StayListComponentTest extends ControllerTestCase
{
    // ── mount: validación de props ──────────────────────────────────────────────

    public function testMountTruncatesLongSearch(): void
    {
        $component         = $this->makeComponent();
        $component->search = str_repeat('x', 300);
        $component->mount();

        self::assertSame(255, mb_strlen($component->search));
    }

    public function testMountClearsInvalidUuidFilters(): void
    {
        $component              = $this->makeComponent();
        $component->familyId    = 'not-a-uuid';
        $component->programmeId = '12345';
        $component->mount();

        self::assertSame('', $component->familyId);
        self::assertSame('', $component->programmeId);
    }

    public function testMountKeepsValidUuidFilters(): void
    {
        $family    = Uuid::v4()->toRfc4122();
        $programme = Uuid::v4()->toRfc4122();

        $component              = $this->makeComponent();
        $component->familyId    = $family;
        $component->programmeId = $programme;
        $component->mount();

        self::assertSame($family, $component->familyId);
        self::assertSame($programme, $component->programmeId);
    }

    public function testMountClampsPageBounds(): void
    {
        $low        = $this->makeComponent();
        $low->page  = 0;
        $low->mount();
        self::assertSame(1, $low->page);

        $high       = $this->makeComponent();
        $high->page = 10_000;
        $high->mount();
        self::assertSame(9999, $high->page);
    }

    // ── LiveActions y helpers de estado ──────────────────────────────────────────

    public function testClearFiltersResetsEverything(): void
    {
        $component              = $this->makeComponent();
        $component->search      = 'algo';
        $component->familyId    = Uuid::v4()->toRfc4122();
        $component->programmeId = Uuid::v4()->toRfc4122();
        $component->showCurrent = false;
        $component->showFuture  = false;
        $component->showPast    = false;
        $component->page        = 5;

        $component->clearFilters();

        self::assertSame('', $component->search);
        self::assertSame('', $component->familyId);
        self::assertSame('', $component->programmeId);
        self::assertTrue($component->showCurrent);
        self::assertTrue($component->showFuture);
        self::assertTrue($component->showPast);
        self::assertSame(1, $component->page);
    }

    public function testChangeFamilyFilterResetsProgrammeAndPage(): void
    {
        $component              = $this->makeComponent();
        $component->programmeId = Uuid::v4()->toRfc4122();
        $component->page        = 3;

        $component->changeFamilyFilter();

        self::assertSame('', $component->programmeId);
        self::assertSame(1, $component->page);
    }

    public function testSetPageNeverBelowOne(): void
    {
        $component = $this->makeComponent();

        $component->setPage(-5);
        self::assertSame(1, $component->page);

        $component->setPage(4);
        self::assertSame(4, $component->page);
    }

    public function testResetPageReturnsToFirst(): void
    {
        $component       = $this->makeComponent();
        $component->page = 7;

        $component->resetPage();

        self::assertSame(1, $component->page);
    }

    public function testHasActiveFiltersDetectsNonDefaults(): void
    {
        $component = $this->makeComponent();
        self::assertFalse($component->hasActiveFilters());

        $component->search = 'foo';
        self::assertTrue($component->hasActiveFilters());

        $component->search   = '';
        $component->showPast = false;
        self::assertTrue($component->hasActiveFilters());
    }

    // ── Paginación contra base de datos ──────────────────────────────────────────

    public function testPageBeyondLastIsClampedToLastPage(): void
    {
        $centre = $this->makeCentreWithStay('42000001', 'Estancia actual', '-5 days', '+5 days');

        $component         = $this->makeComponent($centre);
        $component->page   = 99;

        $pagination = $component->getPagination();

        self::assertSame(1, $pagination->getCurrentPage());
        self::assertSame(1, $component->page);
    }

    public function testPeriodFilterExcludesPastWhenDisabled(): void
    {
        $centre = $this->makeCentreWithStay('42000002', 'Estancia actual', '-5 days', '+5 days');
        $this->addStay($centre, 'Estancia pasada', '-60 days', '-30 days');

        $component              = $this->makeComponent($centre);
        $component->showPast    = false;
        $component->showCurrent = true;
        $component->showFuture  = true;

        $names = array_map(static fn (Stay $s): string => $s->getName(), $component->getItems());

        self::assertContains('Estancia actual', $names);
        self::assertNotContains('Estancia pasada', $names);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function makeComponent(?EducationalCentre $centre = null, ?UserInterface $user = null): StayListComponent
    {
        // Detach in-memory entities so DB-backed queries hydrate fresh instances.
        $this->em->clear();

        // AppSettings resolves overrides through TenantContext, which reads the
        // session; provide a session-bearing request so page.size can be read.
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->setSession(new \Symfony\Component\HttpFoundation\Session\Session(
            new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()
        ));
        /** @var \Symfony\Component\HttpFoundation\RequestStack $stack */
        $stack = self::getContainer()->get('request_stack');
        $stack->push($request);

        /** @var StayRepository $stays */
        $stays = self::getContainer()->get(StayRepository::class);
        /** @var ProfessionalFamilyRepository $families */
        $families = self::getContainer()->get(ProfessionalFamilyRepository::class);
        /** @var ProgrammeRepository $programmes */
        $programmes = self::getContainer()->get(ProgrammeRepository::class);
        /** @var AppSettings $appSettings */
        $appSettings = self::getContainer()->get(AppSettings::class);

        $component = new class($stays, $families, $programmes, $appSettings, $user) extends StayListComponent {
            public function __construct(
                StayRepository $stays,
                ProfessionalFamilyRepository $families,
                ProgrammeRepository $programmes,
                AppSettings $appSettings,
                private readonly ?UserInterface $stubUser,
            ) {
                parent::__construct($stays, $families, $programmes, $appSettings);
            }

            protected function getUser(): ?UserInterface
            {
                return $this->stubUser;
            }
        };

        if ($centre !== null) {
            $reloaded = self::getContainer()->get(\App\Repository\EducationalCentreRepository::class)
                ->find($centre->getId());
            $component->centre = $reloaded ?? $centre;
        }

        return $component;
    }

    private function makeCentreWithStay(string $code, string $name, string $start, string $end): EducationalCentre
    {
        $centre = (new EducationalCentre())->setCode($code)->setName('IES ' . $code)->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);
        $this->persist($centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();

        $this->addStay($centre, $name, $start, $end);

        return $centre;
    }

    private function addStay(EducationalCentre $centre, string $name, string $start, string $end): Stay
    {
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
        $this->flush();

        return $stay;
    }
}
