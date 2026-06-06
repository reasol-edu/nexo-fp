<?php

namespace App\Controller;

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
        $year   = $centre?->getActiveAcademicYear();

        if ($centre === null || $year === null) {
            return $this->render('dashboard/index.html.twig', [
                'stats'           => null,
                'studentCount'    => 0,
                'upcomingStays'   => [],
            ]);
        }

        return $this->render('dashboard/index.html.twig', [
            'stats'         => $this->stayRepository->findDashboardStats($year),
            'studentCount'  => $this->studentRepository->countByActiveYear($centre),
            'upcomingStays' => $this->stayRepository->findActiveAndUpcoming($year),
        ]);
    }
}
