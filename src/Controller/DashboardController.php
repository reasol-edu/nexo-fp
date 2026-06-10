<?php

namespace App\Controller;

use App\Entity\AcademicYear;
use App\Entity\Teacher;
use App\Repository\StayRepository;
use App\Repository\StudentRepository;
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
                'stats'           => null,
                'studentCount'    => 0,
                'upcomingStays'   => [],
                'alerts'          => [],
            ]);
        }

        $user   = $this->getUser();
        $viewer = $user instanceof Teacher ? $user : null;

        return $this->render('dashboard/index.html.twig', [
            'stats'         => $this->stayRepository->findDashboardStats($year, $viewer),
            'studentCount'  => $this->studentRepository->countByActiveYear($centre, $viewer),
            'upcomingStays' => $this->stayRepository->findActiveAndUpcoming($year, $viewer),
            'alerts'        => $this->buildAlerts($year, $viewer),
        ]);
    }

    /**
     * Merges position alerts and unassigned-student counters into a single
     * per-stay list ordered by end date (soonest first, open-ended last).
     *
     * @return list<array{stay: \App\Entity\Stay, free: int, missing_tutor: int, missing_mentor: int, done_unsigned: int, students_without_position: int}>
     */
    private function buildAlerts(AcademicYear $year, ?Teacher $viewer): array
    {
        $alerts = [];

        foreach ($this->stayRepository->findPositionAlertsByStay($year, $viewer) as $row) {
            $alerts[$row['stay']->getId()->toRfc4122()] = $row + ['students_without_position' => 0];
        }

        foreach ($this->stayRepository->countStudentsWithoutPositionByStay($year, $viewer) as $row) {
            $id = $row['stay']->getId()->toRfc4122();
            if (isset($alerts[$id])) {
                $alerts[$id]['students_without_position'] = $row['students_without_position'];
            } else {
                $alerts[$id] = [
                    'stay'                      => $row['stay'],
                    'free'                      => 0,
                    'missing_tutor'             => 0,
                    'missing_mentor'            => 0,
                    'done_unsigned'             => 0,
                    'students_without_position' => $row['students_without_position'],
                ];
            }
        }

        $alerts = array_values($alerts);
        usort($alerts, static function (array $a, array $b): int {
            $aEnd = $a['stay']->getEndDate();
            $bEnd = $b['stay']->getEndDate();
            if ($aEnd === null && $bEnd === null) {
                return 0;
            }
            if ($aEnd === null) {
                return 1;
            }
            if ($bEnd === null) {
                return -1;
            }

            return $aEnd <=> $bEnd;
        });

        return $alerts;
    }
}
