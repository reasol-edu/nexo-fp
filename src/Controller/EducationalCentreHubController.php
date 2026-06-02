<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class EducationalCentreHubController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    #[Route('/mi-centro', name: 'app_educational_centre_index')]
    public function index(): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        return $this->render('educational_centre/index.html.twig', [
            'centre' => $centre,
        ]);
    }
}
