<?php

namespace App\Controller;

use App\Entity\AcademicYear;
use App\Entity\Teacher;
use App\Repository\StayRepository;
use App\Repository\StudentRepository;
use App\Service\PendingTasksProvider;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StayRepository $stayRepository,
        private readonly StudentRepository $studentRepository,
        private readonly PendingTasksProvider $pendingTasksProvider,
    ) {}

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $year = $centre->getActiveAcademicYear();

        if ($year === null) {
            return $this->render('dashboard/index.html.twig', [
                'stats'             => null,
                'studentCount'      => 0,
                'upcomingStays'     => [],
                'alerts'            => [],
                'familyStats'       => [],
                'signaturesByMonth' => [],
            ]);
        }

        $user   = $this->getUser();
        $viewer = $user instanceof Teacher ? $user : null;

        return $this->render('dashboard/index.html.twig', [
            'stats'              => $this->stayRepository->findDashboardStats($year, $viewer),
            'studentCount'       => $this->studentRepository->countByActiveYear($centre, $viewer),
            'upcomingStays'      => $this->stayRepository->findActiveAndUpcoming($year, $viewer),
            'alerts'             => $this->pendingTasksProvider->findAlertsByStay($year, $viewer),
            'familyStats'        => $this->stayRepository->countPositionsByFamily($year, $viewer),
            'signaturesByMonth'  => $this->buildSignaturesByMonth($year, $viewer),
        ]);
    }

    /** @return list<array{label: string, count: int}> */
    private function buildSignaturesByMonth(AcademicYear $year, ?Teacher $viewer): array
    {
        $dates = $this->stayRepository->findSignedDatesForYear($year, $viewer);
        if (empty($dates)) {
            return [];
        }

        $byMonth = [];
        foreach ($dates as $date) {
            $key = $date->format('Y-m');
            $byMonth[$key] = ($byMonth[$key] ?? 0) + 1;
        }

        $cursor = new \DateTimeImmutable(array_key_first($byMonth) . '-01');
        $last   = new \DateTimeImmutable(array_key_last($byMonth) . '-01');
        $result = [];
        while ($cursor <= $last) {
            $key      = $cursor->format('Y-m');
            $result[] = ['label' => $cursor->format('m/y'), 'count' => $byMonth[$key] ?? 0];
            $cursor   = $cursor->modify('+1 month');
        }

        return $result;
    }
}
