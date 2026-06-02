<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Company;
use App\Entity\PersonName;
use App\Entity\Worker;
use App\Entity\Workcenter;
use App\Pagination\Paginator;
use App\Repository\CompanyRepository;
use App\Repository\TeacherRepository;
use App\Repository\WorkcenterRepository;
use App\Repository\WorkerRepository;
use App\Security\Voter\CompanyVoter;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/empresas')]
class CompanyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CompanyRepository $companies,
        private readonly WorkcenterRepository $workcenters,
        private readonly WorkerRepository $workers,
        private readonly TeacherRepository $teachers,
        private readonly TenantContext $tenantContext,
        private readonly TranslatorInterface $translator,
        #[Autowire(env: 'int:APP_PAGE_SIZE')] private readonly int $pageSize,
    ) {}

    #[Route('', name: 'app_companies_index')]
    public function index(): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $this->denyAccessUnlessGranted(CompanyVoter::SECTION, $centre);

        return $this->render('company/index.html.twig');
    }

    #[Route('/nueva', name: 'app_companies_new')]
    public function new(Request $request): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $this->denyAccessUnlessGranted(CompanyVoter::SECTION, $centre);

        $errors = [];
        $values = ['name' => '', 'vat_number' => '', 'city' => ''];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_company', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'name'       => trim($request->request->getString('name')),
                'vat_number' => trim($request->request->getString('vat_number')),
                'city'       => trim($request->request->getString('city')),
            ];

            $errors = $this->validateCompany($values);

            if (empty($errors['vat_number'])) {
                $existing = $this->companies->findByVatNumberAndCentre($values['vat_number'], $centre);
                if ($existing !== null) {
                    $errors['vat_number'] = $this->t('company.error.vat_number_duplicate');
                }
            }

            if (empty($errors)) {
                $company = (new Company())
                    ->setName($values['name'])
                    ->setVatNumber($values['vat_number'])
                    ->setCity($values['city'])
                    ->setEducationalCentre($centre);

                $this->em->persist($company);

                $workcenter = (new Workcenter())
                    ->setName($this->t('workcenter.default_name'))
                    ->setCity($values['city'])
                    ->setCompany($company);

                $this->em->persist($workcenter);
                $this->em->flush();

                $this->addFlash('success', $this->t('company.flash.created'));

                return $this->redirectToRoute('app_companies_edit', ['id' => $company->getId()->toRfc4122()]);
            }
        }

        return $this->render('company/new.html.twig', [
            'errors' => $errors,
            'values' => $values,
        ]);
    }

    #[Route('/{id}', name: 'app_companies_edit')]
    public function edit(string $id, Request $request): Response
    {
        $company = $this->requireCompanyInCurrentCentre($id);
        $this->denyAccessUnlessGranted(CompanyVoter::EDIT, $company);

        $errors = [];
        $values = [
            'name'                      => $company->getName(),
            'vat_number'                => $company->getVatNumber(),
            'city'                      => $company->getCity(),
            'exceptional_circumstances' => $company->getExceptionalCircumstances() ?? '',
        ];

        /** @var \App\Entity\Teacher[] $selectedLiaisons */
        $selectedLiaisons = $company->getLiaisons()->toArray();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_company_' . $id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'name'                      => trim($request->request->getString('name')),
                'vat_number'                => trim($request->request->getString('vat_number')),
                'city'                      => trim($request->request->getString('city')),
                'exceptional_circumstances' => trim($request->request->getString('exceptional_circumstances')),
            ];

            $errors = $this->validateCompany($values);

            if (empty($errors['vat_number'])) {
                $existing = $this->companies->findByVatNumberAndCentre($values['vat_number'], $company->getEducationalCentre());
                if ($existing !== null && !$existing->getId()->equals($company->getId())) {
                    $errors['vat_number'] = $this->t('company.error.vat_number_duplicate');
                }
            }

            $submittedIds = array_values(array_filter(
                array_map(
                    static fn(mixed $v): string => \is_string($v) ? $v : '',
                    $request->request->all('liaisons')
                ),
                static fn(string $v): bool => $v !== ''
            ));

            if (!empty($errors)) {
                $selectedLiaisons = [];
                foreach ($submittedIds as $teacherId) {
                    $teacher = $this->teachers->findById($teacherId);
                    if ($teacher !== null) {
                        $selectedLiaisons[] = $teacher;
                    }
                }
            } else {
                $company->setName($values['name'])
                    ->setVatNumber($values['vat_number'])
                    ->setCity($values['city'])
                    ->setExceptionalCircumstances($values['exceptional_circumstances'] !== '' ? $values['exceptional_circumstances'] : null);

                foreach ($company->getLiaisons()->toArray() as $liaison) {
                    $company->removeLiaison($liaison);
                }
                foreach ($submittedIds as $teacherId) {
                    $teacher = $this->teachers->findById($teacherId);
                    if ($teacher !== null) {
                        $company->addLiaison($teacher);
                    }
                }

                $this->em->flush();

                $this->addFlash('success', $this->t('company.flash.saved'));

                return $this->redirectToRoute('app_companies_edit', ['id' => $id]);
            }
        }

        return $this->render('company/edit.html.twig', [
            'company'          => $company,
            'workcenters'      => $this->workcenters->findByCompanyOrderedByName($company),
            'workers'          => $company->getWorkers()->toArray(),
            'errors'           => $errors,
            'values'           => $values,
            'selectedLiaisons' => $selectedLiaisons,
            'canDelete'        => $this->isGranted(CompanyVoter::DELETE, $company),
        ]);
    }

    #[Route('/{id}/eliminar', name: 'app_companies_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_company_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $company = $this->requireCompanyInCurrentCentre($id);
        $this->denyAccessUnlessGranted(CompanyVoter::DELETE, $company);

        try {
            foreach ($this->workcenters->findByCompanyOrderedByName($company) as $workcenter) {
                $this->em->remove($workcenter);
            }
            $this->em->remove($company);
            $this->em->flush();
            $this->addFlash('success', $this->t('company.flash.deleted'));
        } catch (\Exception) {
            $this->addFlash('error', $this->t('company.flash.delete_error'));
        }

        return $this->redirectToRoute('app_companies_index');
    }

    #[Route('/{id}/centros-trabajo', name: 'app_companies_workcenter_add', methods: ['POST'])]
    public function addWorkcenter(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('add_workcenter_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $company = $this->requireCompanyInCurrentCentre($id);
        $this->denyAccessUnlessGranted(CompanyVoter::EDIT, $company);

        $name = trim($request->request->getString('name'));
        $city = trim($request->request->getString('city'));

        if ($name === '') {
            $this->addFlash('error', $this->t('workcenter.flash.name_required'));
        } elseif ($city === '') {
            $this->addFlash('error', $this->t('workcenter.flash.city_required'));
        } else {
            $workcenter = (new Workcenter())
                ->setName($name)
                ->setCity($city)
                ->setCompany($company);

            $this->em->persist($workcenter);
            $this->em->flush();

            $this->addFlash('success', $this->t('workcenter.flash.added'));
        }

        return $this->redirectToRoute('app_companies_edit', ['id' => $id]);
    }

    #[Route('/{companyId}/centros-trabajo/{workcenterID}', name: 'app_companies_workcenter_edit')]
    public function editWorkcenter(string $companyId, string $workcenterID): Response
    {
        $company = $this->requireCompanyInCurrentCentre($companyId);
        $this->denyAccessUnlessGranted(CompanyVoter::EDIT, $company);

        $workcenter = $this->workcenters->findByCompanyAndId($company, $workcenterID);
        if ($workcenter === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('company/edit_workcenter.html.twig', [
            'company'    => $company,
            'workcenter' => $workcenter,
        ]);
    }

    #[Route('/{companyId}/centros-trabajo/{workcenterID}/eliminar', name: 'app_companies_workcenter_delete', methods: ['POST'])]
    public function deleteWorkcenter(string $companyId, string $workcenterID, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_workcenter_' . $workcenterID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $company = $this->requireCompanyInCurrentCentre($companyId);
        $this->denyAccessUnlessGranted(CompanyVoter::EDIT, $company);

        $workcenter = $this->workcenters->findByCompanyAndId($company, $workcenterID);
        if ($workcenter === null) {
            throw $this->createNotFoundException();
        }

        try {
            $this->em->remove($workcenter);
            $this->em->flush();
            $this->addFlash('success', $this->t('workcenter.flash.deleted'));
        } catch (\Exception) {
            $this->addFlash('error', $this->t('workcenter.flash.delete_error'));
        }

        return $this->redirectToRoute('app_companies_edit', ['id' => $companyId]);
    }

    #[Route('/{id}/empleados', name: 'app_companies_worker_add', methods: ['POST'])]
    public function addWorker(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('add_worker_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $company = $this->requireCompanyInCurrentCentre($id);
        $this->denyAccessUnlessGranted(CompanyVoter::EDIT, $company);

        $firstName  = trim($request->request->getString('first_name'));
        $lastName   = trim($request->request->getString('last_name'));
        $nationalId = trim($request->request->getString('national_id'));

        if ($firstName === '') {
            $this->addFlash('error', $this->t('worker.error.first_name_required'));
        } elseif ($lastName === '') {
            $this->addFlash('error', $this->t('worker.error.last_name_required'));
        } elseif ($nationalId === '') {
            $this->addFlash('error', $this->t('worker.error.national_id_required'));
        } else {
            $worker = $this->workers->findByNationalIdNumber($nationalId);

            if ($worker === null) {
                $worker = new Worker(new PersonName($firstName, $lastName));
                $worker->setNationalIdNumber($nationalId);
                $worker->setWorkEmail($request->request->getString('work_email') !== '' ? trim($request->request->getString('work_email')) : null);
                $worker->setWorkPhoneNumber($request->request->getString('work_phone') !== '' ? trim($request->request->getString('work_phone')) : null);
                $this->em->persist($worker);
                $flash = 'worker.flash.added';
            } else {
                $flash = 'worker.flash.linked';
            }

            $company->addWorker($worker);
            $this->em->flush();

            $this->addFlash('success', $this->t($flash));
        }

        return $this->redirectToRoute('app_companies_edit', ['id' => $id]);
    }

    #[Route('/{id}/empleados/{workerID}', name: 'app_companies_worker_edit')]
    public function editWorker(string $id, string $workerID): Response
    {
        $company = $this->requireCompanyInCurrentCentre($id);
        $this->denyAccessUnlessGranted(CompanyVoter::EDIT, $company);

        $worker = $this->workers->find($workerID);
        if ($worker === null || !$company->getWorkers()->contains($worker)) {
            throw $this->createNotFoundException();
        }

        return $this->render('company/edit_worker.html.twig', [
            'company' => $company,
            'worker'  => $worker,
        ]);
    }

    #[Route('/{id}/empleados/{workerID}/eliminar', name: 'app_companies_worker_remove', methods: ['POST'])]
    public function removeWorker(string $id, string $workerID, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('remove_worker_' . $workerID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $company = $this->requireCompanyInCurrentCentre($id);
        $this->denyAccessUnlessGranted(CompanyVoter::EDIT, $company);

        $worker = $this->workers->find($workerID);
        if ($worker !== null && $company->getWorkers()->contains($worker)) {
            $company->removeWorker($worker);
            $this->em->flush();
        }

        $this->addFlash('success', $this->t('worker.flash.removed'));

        return $this->redirectToRoute('app_companies_edit', ['id' => $id]);
    }

    private function requireCompanyInCurrentCentre(string $id): Company
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            throw $this->createNotFoundException();
        }

        $company = $this->companies->findByIdAndCentre($id, $centre);
        if ($company === null) {
            throw $this->createNotFoundException();
        }

        return $company;
    }

    /**
     * @param  array<string, string> $values
     * @return array<string, string>
     */
    private function validateCompany(array $values): array
    {
        $errors = [];

        if ($values['name'] === '') {
            $errors['name'] = $this->t('company.error.name_required');
        }

        if ($values['vat_number'] === '') {
            $errors['vat_number'] = $this->t('company.error.vat_number_required');
        }

        if ($values['city'] === '') {
            $errors['city'] = $this->t('company.error.city_required');
        }

        return $errors;
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'companies');
    }
}
