<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CompanyRepository;
use App\Repository\StayRepository;
use App\Repository\TeacherRepository;
use App\Security\Voter\EducationalCentreVoter;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EducationalCentreHubController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TeacherRepository $teachers,
        private readonly CompanyRepository $companies,
        private readonly StayRepository $stays,
        private readonly ClockInterface $clock,
    ) {}

    #[Route('/mi-centro', name: 'app_educational_centre_index')]
    public function index(): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);

        return $this->render('educational_centre/index.html.twig', [
            'centre' => $centre,
        ]);
    }

    #[Route('/mi-centro/ajustes', name: 'app_educational_centre_settings')]
    public function settings(): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);

        return $this->render('educational_centre/settings.html.twig', [
            'centre' => $centre,
        ]);
    }

    /** Datos que conviene completar para que los avisos y los tutores de empresa funcionen. */
    #[Route('/mi-centro/revision-datos', name: 'app_educational_centre_data_review')]
    public function dataReview(): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);

        $year = $this->tenantContext->getViewYear($centre);

        return $this->render('educational_centre/data_review.html.twig', [
            'centre'                   => $centre,
            'year'                     => $year,
            'teachers_without_email'   => $year !== null ? $this->teachers->findActiveWithoutEmailByAcademicYear($year) : [],
            'companies_without_workers' => $year !== null ? $this->companies->findWithPositionsInYearWithoutWorkers($centre, $year) : [],
            'stays_with_drafts'        => $year !== null
                ? $this->stays->findWithDraftPositionsStartingBefore($year, $this->clock->now()->modify('+14 days')->setTime(0, 0, 0))
                : [],
        ]);
    }

    #[Route('/mi-centro/registro-correos', name: 'app_educational_centre_email_log')]
    public function emailLog(): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);

        return $this->render('educational_centre/email_log.html.twig', [
            'centre' => $centre,
        ]);
    }
}
