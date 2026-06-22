<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EducationalCentre;
use App\Repository\EducationalCentreRepository;
use App\Service\ImportOptions;
use App\Service\OfertaFormativaExporter;
use App\Service\OfertaFormativaImporter;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\EducationalCentreVoter;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/centros/{centreId}/familias')]
class ProfessionalFamilyController extends AbstractController
{
    public function __construct(
        private readonly EducationalCentreRepository $centres,
        private readonly TranslatorInterface $translator,
        private readonly OfertaFormativaExporter $exporter,
        private readonly OfertaFormativaImporter $importer,
        private readonly TenantContext $tenantContext,
    ) {}

    // ── Exportación / Importación JSON ────────────────────────────────────────

    #[Route('/exportar', name: 'app_admin_families_export', methods: ['GET'])]
    public function export(string $centreId): JsonResponse
    {
        $centre = $this->requireCentreWithActiveYear($centreId);

        $data     = $this->exporter->export($centre->getActiveAcademicYear());
        $filename = 'oferta-' . $centre->getCode() . '-' . $centre->getActiveAcademicYear()->getName() . '.json';
        $json     = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new JsonResponse($json, Response::HTTP_OK, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], true);
    }

    #[Route('/importar', name: 'app_admin_families_import')]
    public function import(string $centreId, Request $request): Response
    {
        $centre = $this->requireCentreForWrite($centreId);

        if (!$request->isMethod('POST')) {
            return $this->render('admin/family/import.html.twig', ['centre' => $centre]);
        }

        if (!$this->isCsrfTokenValid('import_oferta', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $file = $request->files->get('json');
        if ($file === null || !$file->isValid()) {
            $this->addFlash('error', $this->t('families.import.error.no_file'));
            return $this->render('admin/family/import.html.twig', ['centre' => $centre]);
        }

        $content = (string) file_get_contents($file->getPathname());
        $data    = json_decode($content, true);

        if (!is_array($data)) {
            $this->addFlash('error', $this->t('families.import.error.invalid_json'));
            return $this->render('admin/family/import.html.twig', ['centre' => $centre]);
        }

        $options = new ImportOptions(
            importHeads:    $request->request->has('import_heads'),
            importTutors:   $request->request->has('import_tutors'),
            importTeachers: $request->request->has('import_teachers'),
        );

        $stats = $this->importer->import($data, $centre->getActiveAcademicYear(), $options);

        $summary = $this->translator->trans('families.import.flash.summary', [
            '%families%'  => $stats['families'],
            '%programmes%' => $stats['programmes'],
            '%levels%'    => $stats['levels'],
            '%groups%'    => $stats['groups'],
        ], 'admin');

        if (!empty($stats['missing_teachers'])) {
            $summary .= ' ' . $this->translator->trans('families.import.flash.missing_teachers', [
                '%teachers%' => implode(', ', array_map(
                    static fn(string $u) => '«' . $u . '»',
                    $stats['missing_teachers'],
                )),
            ], 'admin');
        }

        $this->addFlash('success', $summary);

        return $this->redirectToRoute('app_admin_families_index', ['centreId' => $centreId]);
    }

    // ── Familias ──────────────────────────────────────────────────────────────

    #[Route('', name: 'app_admin_families_index')]
    public function index(string $centreId): Response
    {
        $centre = $this->requireCentre($centreId);

        return $this->render('admin/family/index.html.twig', ['centre' => $centre]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireCentre(string $centreId): EducationalCentre
    {
        $centre = $this->centres->findByIdWithActiveYear($centreId);
        if ($centre === null) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);

        return $centre;
    }

    private function requireCentreWithActiveYear(string $centreId): EducationalCentre
    {
        $centre = $this->requireCentre($centreId);
        if ($centre->getActiveAcademicYear() === null) {
            throw $this->createNotFoundException();
        }

        return $centre;
    }

    private function requireCentreForWrite(string $centreId): EducationalCentre
    {
        $centre = $this->requireCentreWithActiveYear($centreId);
        if ($this->tenantContext->isViewingNonActiveYear($centre)) {
            throw $this->createAccessDeniedException('Write operations are not allowed when viewing a past academic year.');
        }

        return $centre;
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'admin');
    }
}
