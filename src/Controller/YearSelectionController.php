<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AcademicYearRepository;
use App\Security\Voter\EducationalCentreVoter;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEACHER')]
class YearSelectionController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AcademicYearRepository $years,
    ) {}

    #[Route('/curso/año', name: 'app_select_year_page')]
    public function page(Request $request): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);

        // También se usa como enlace «Volver»: un «javascript:…» o «//otro.sitio» no debe llegar al href.
        $returnTo = $this->safeLocalPath($request->query->getString('return_to', '/')) ?? '/';

        return $this->render('year_selection/index.html.twig', [
            'centre'      => $centre,
            'years'       => $this->years->findByCentreOrderedByName($centre),
            'active_year' => $centre->getActiveAcademicYear(),
            'view_year'   => $this->tenantContext->getViewYear($centre),
            'return_to'   => $returnTo,
        ]);
    }

    #[Route('/curso/año/{id}', name: 'app_select_year', methods: ['POST'],
        requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function select(string $id, Request $request): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);

        if (!$this->isCsrfTokenValid('select_year_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $year = $this->years->findByCentreAndId($centre, $id);
        if ($year === null) {
            throw $this->createNotFoundException();
        }

        // Switching to the active year is the same as resetting
        $activeYear = $centre->getActiveAcademicYear();
        if ($activeYear !== null && $year->getId()->toRfc4122() === $activeYear->getId()->toRfc4122()) {
            $this->tenantContext->clearYear();
        } else {
            $this->tenantContext->selectYear($year);
        }

        return $this->redirect($this->resolveReturnTo($request));
    }

    #[Route('/curso/año/activo', name: 'app_reset_year', methods: ['POST'])]
    public function reset(Request $request): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre !== null) {
            $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);
        }

        if (!$this->isCsrfTokenValid('reset_year', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->tenantContext->clearYear();

        return $this->redirect($this->resolveReturnTo($request));
    }

    private function resolveReturnTo(Request $request): string
    {
        return $this->safeLocalPath($request->request->getString('_return_to', ''))
            ?? $this->generateUrl('app_dashboard');
    }

    /**
     * Devuelve la ruta solo si apunta a una página de esta misma aplicación. Rechaza las rutas
     * relativas al protocolo («//otro.sitio», «/\otro.sitio»), que el navegador interpretaría como
     * otro dominio, y los caracteres de control (inyección de cabeceras).
     */
    private function safeLocalPath(string $path): ?string
    {
        if (preg_match('#^/(?![/\\\\])#', $path) === 1 && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1) {
            return $path;
        }

        return null;
    }
}
