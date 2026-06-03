<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Stay;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\StayRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/estancias')]
#[IsGranted('ROLE_TEACHER')]
class StayController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenant,
        private readonly StayRepository $stays,
        private readonly ProfessionalFamilyRepository $families,
        private readonly ProgrammeRepository $programmes,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_stays_index')]
    public function index(): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        return $this->render('stays/index.html.twig', ['centre' => $centre]);
    }

    #[Route('/nueva', name: 'app_stays_new')]
    public function new(Request $request): Response
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $year = $centre->getActiveAcademicYear();
        if ($year === null) {
            $this->addFlash('error', $this->t('stays.flash.no_active_year'));

            return $this->redirectToRoute('app_stays_index');
        }

        $allProgrammes = $this->programmes->findByAcademicYearOrderedByFamilyAndName($year);

        /** @var array<string, array{family: \App\Entity\ProfessionalFamily, programmes: \App\Entity\Programme[]}> $byFamily */
        $byFamily = [];
        foreach ($allProgrammes as $p) {
            $fid = $p->getProfessionalFamily()->getId()->toRfc4122();
            if (!isset($byFamily[$fid])) {
                $byFamily[$fid] = ['family' => $p->getProfessionalFamily(), 'programmes' => []];
            }
            $byFamily[$fid]['programmes'][] = $p;
        }

        $errors = [];
        $values = ['name' => '', 'programme_id' => ''];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_stay', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'name'         => trim($request->request->getString('name')),
                'programme_id' => trim($request->request->getString('programme_id')),
            ];

            if ($values['name'] === '') {
                $errors['name'] = $this->t('stays.error.name_required');
            }

            $programme = null;
            if ($values['programme_id'] !== '') {
                $programme = $this->programmes->findByAcademicYearAndId($year, $values['programme_id']);
            }
            if ($programme === null) {
                $errors['programme_id'] = $this->t('stays.error.programme_required');
            }

            if (empty($errors) && $programme !== null) {
                $stay = new Stay();
                $stay->setName($values['name'])
                     ->setAcademicYear($year)
                     ->setProgramme($programme);

                $this->em->persist($stay);
                $this->em->flush();

                $this->addFlash('success', $this->t('stays.flash.created'));

                return $this->redirectToRoute('app_stays_index');
            }
        }

        return $this->render('stays/new.html.twig', [
            'centre'    => $centre,
            'by_family' => $byFamily,
            'errors'    => $errors,
            'values'    => $values,
        ]);
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'stays');
    }
}
