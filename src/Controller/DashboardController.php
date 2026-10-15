<?php

namespace App\Controller;

use App\Dashboard\DashboardBuilder;
use App\Dashboard\DashboardService;
use App\Entity\Teacher;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    private const FUNNEL_MODES = [DashboardBuilder::FUNNEL_ACTIVE, DashboardBuilder::FUNNEL_UPCOMING, DashboardBuilder::FUNNEL_ALL];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DashboardService $dashboard,
    ) {}

    #[Route('/', name: 'app_dashboard')]
    public function index(Request $request): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $year = $this->tenantContext->getViewYear($centre);
        $user = $this->getUser();

        if ($year === null || !$user instanceof Teacher) {
            return $this->render('dashboard/index.html.twig', ['page' => null]);
        }

        $mode = $request->query->getString('avance');

        return $this->render('dashboard/index.html.twig', [
            'page' => $this->dashboard->build($centre, $year, $user, \in_array($mode, self::FUNNEL_MODES, true) ? $mode : DashboardBuilder::FUNNEL_ACTIVE),
        ]);
    }
}
