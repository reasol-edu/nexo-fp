<?php

namespace App\Controller;

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
            ]);
        }

        $user   = $this->getUser();
        $viewer = $user instanceof Teacher ? $user : null;

        return $this->render('dashboard/index.html.twig', [
            'stats'         => $this->stayRepository->findDashboardStats($year, $viewer),
            'studentCount'  => $this->studentRepository->countByActiveYear($centre, $viewer),
            'upcomingStays' => $this->stayRepository->findActiveAndUpcoming($year, $viewer),
        ]);
    }
}
