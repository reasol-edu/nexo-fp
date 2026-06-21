<?php

namespace App\Controller;

use App\Entity\Teacher;
use App\Repository\StayRepository;
use App\Repository\StudentRepository;
use App\Security\Voter\EducationalCentreVoter;
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

        $year = $this->tenantContext->getViewYear($centre);

        if ($year === null) {
            return $this->render('dashboard/index.html.twig', [
                'stats'             => null,
                'studentCount'      => 0,
                'upcomingStays'     => [],
                'alerts'            => [],
                'familyStats'       => [],
                'studentFamilyStats' => [],
            ]);
        }

        $user   = $this->getUser();
        $viewer = $user instanceof Teacher ? $user : null;

        // The by-family breakdowns are only shown to global and centre admins.
        $canSeeFamilyStats = $this->isGranted(EducationalCentreVoter::SECTION, $centre);

        return $this->render('dashboard/index.html.twig', [
            'stats'              => $this->stayRepository->findDashboardStats($year, $viewer),
            'studentCount'       => $this->studentRepository->countByActiveYear($centre, $viewer, $year),
            'upcomingStays'      => $this->stayRepository->findActiveAndUpcoming($year, $viewer),
            'alerts'             => $this->pendingTasksProvider->findAlertsByStay($year, $viewer),
            'familyStats'        => $canSeeFamilyStats ? $this->stayRepository->countPositionsByFamily($year, $viewer) : [],
            'studentFamilyStats' => $canSeeFamilyStats ? $this->stayRepository->countStudentsByFamilyState($year, $viewer) : [],
        ]);
    }
}
