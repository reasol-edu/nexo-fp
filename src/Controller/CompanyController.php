<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Company;
use App\Entity\PersonName;
use App\Entity\Worker;
use App\Entity\Workcenter;
use App\Pagination\Paginator;
use App\Repository\CompanyAuditRepository;
use App\Repository\CompanyRepository;
use App\Repository\TeacherRepository;
use App\Repository\WorkcenterRepository;
use App\Repository\WorkerRepository;
use App\Security\Voter\CompanyVoter;
use App\Service\CompanyXlsxExporter;
use App\Service\CompanyXlsxImporter;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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
        private readonly CompanyAuditRepository $companyAudits,
        private readonly TenantContext $tenantContext,
        private readonly TranslatorInterface $translator,
        private readonly ValidatorInterface $validator,
        private readonly CompanyXlsxExporter $companyExporter,
        private readonly CompanyXlsxImporter $companyImporter,
        private readonly ClockInterface $clock,
        #[Autowire(service: 'html_sanitizer.sanitizer.app.company_contact')]
        private readonly HtmlSanitizerInterface $contactSanitizer,
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
        $values = [
            'name'                       => '',
            'vat_number'                 => '',
            'city'                       => '',
            'representative_first_name'  => '',
            'representative_last_name'   => '',
            'representative_national_id' => '',
            'representative_role'        => '',
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_company', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'name'                       => trim($request->request->getString('name')),
                'vat_number'                 => trim($request->request->getString('vat_number')),
                'city'                       => trim($request->request->getString('city')),
                'representative_first_name'  => trim($request->request->getString('representative_first_name')),
                'representative_last_name'   => trim($request->request->getString('representative_last_name')),
                'representative_national_id' => trim($request->request->getString('representative_national_id')),
                'representative_role'        => trim($request->request->getString('representative_role')),
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
                    ->setRepresentativeFirstName($values['representative_first_name'] !== '' ? $values['representative_first_name'] : null)
                    ->setRepresentativeLastName($values['representative_last_name'] !== '' ? $values['representative_last_name'] : null)
                    ->setRepresentativeNationalId($values['representative_national_id'] !== '' ? $values['representative_national_id'] : null)
                    ->setRepresentativeRole($values['representative_role'] !== '' ? $values['representative_role'] : null)
                    ->setEducationalCentre($centre);

                $errors = $this->mapViolations($this->validator->validate($company));

                if (empty($errors)) {
                    $workcenter = (new Workcenter())
                        ->setName($this->t('workcenter.default_name'))
                        ->setCity($values['city'])
                        ->setCompany($company);

                    $this->em->persist($company);
                    $this->em->persist($workcenter);
                    $this->em->flush();

                    $this->addFlash('success', $this->t('company.flash.created'));

                    return $this->redirectToRoute('app_companies_edit', ['id' => $company->getId()->toRfc4122()]);
                }
            }
        }

        return $this->render('company/new.html.twig', [
            'errors' => $errors,
            'values' => $values,
        ]);
    }

    // Debe declararse antes de edit(): su ruta /{id} capturaría /exportar
    #[Route('/exportar', name: 'app_companies_export')]
    public function export(Request $request): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $this->denyAccessUnlessGranted(CompanyVoter::SECTION, $centre);

        $search = trim($request->query->getString('search'));

        $companies = array_map(
            static fn (array $row): Company => $row['company'],
            $this->companies->findByCentreFilteredForExport($centre, $search),
        );

        return $this->companyExporter->createResponse(
            'empresas-' . $centre->getCode() . '-' . $this->clock->now()->format('Y-m-d') . '.xlsx',
            $companies,
            $this->workcenters->findByCentreOrdered($centre),
            $this->workers->findGroupedByCompanies($companies),
        );
    }

    /** Libro vacío con la estructura y las instrucciones, para rellenarlo a mano desde cero. */
    #[Route('/plantilla', name: 'app_companies_template')]
    public function template(): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $this->denyAccessUnlessGranted(CompanyVoter::SECTION, $centre);

        return $this->companyExporter->createResponse('plantilla-empresas.xlsx', [], [], []);
    }

    #[Route('/importar', name: 'app_companies_import')]
    public function import(Request $request): Response
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            return $this->redirectToRoute('app_select_centre');
        }

        $this->denyAccessUnlessGranted(CompanyVoter::SECTION, $centre);

        if (!$request->isMethod('POST')) {
            return $this->render('company/import.html.twig');
        }

        // Paso 2: confirmación de una simulación previa
        if ($request->request->getString('import_confirmed') === '1') {
            if (!$this->isCsrfTokenValid('import_companies_confirm', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $importId = $request->request->getString('import_id');
            $session  = $request->getSession();
            $path     = $this->getTempImportPath($importId);

            if ($importId === '' || $importId !== $session->get('company_import_id') || !is_file($path)) {
                $this->addFlash('error', $this->t('companies.import.error.expired'));

                return $this->redirectToRoute('app_companies_import');
            }

            $session->remove('company_import_id');
            $result = $this->companyImporter->import($path, $centre, apply: true);
            @unlink($path);

            if ($result->hasErrors()) {
                return $this->render('company/import.html.twig', ['errors' => $result->errors]);
            }

            $this->addFlash('success', $this->translator->trans('companies.import.flash.done', [
                '%created%' => $result->companiesCreated,
                '%updated%' => $result->companiesUpdated,
            ], 'companies'));

            return $this->redirectToRoute('app_companies_index');
        }

        // Paso 1: subida del fichero y simulación
        if (!$this->isCsrfTokenValid('import_companies', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $file = $request->files->get('xlsx');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('error', $this->t('companies.import.error.no_file'));

            return $this->render('company/import.html.twig');
        }
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            return $this->render('company/import.html.twig', ['errors' => [$this->t('companies.import.error.not_xlsx')]]);
        }

        $importId = Uuid::v4()->toRfc4122();
        $path     = $this->getTempImportPath($importId);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $file->move(dirname($path), basename($path));

        $result = $this->companyImporter->import($path, $centre, apply: false);

        if ($result->hasErrors() || !$result->hasChanges()) {
            @unlink($path);

            return $this->render('company/import.html.twig', [
                'errors' => $result->errors,
                'notice' => $result->hasErrors() ? null : $this->t('companies.import.nothing_to_import'),
            ]);
        }

        $request->getSession()->set('company_import_id', $importId);

        return $this->render('company/import_preview.html.twig', [
            'importId' => $importId,
            'result'   => $result,
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
            'contact_information'       => $company->getContactInformation() ?? '',
            'exceptional_circumstances' => $company->getExceptionalCircumstances() ?? '',
            'representative_first_name' => $company->getRepresentativeFirstName() ?? '',
            'representative_last_name'  => $company->getRepresentativeLastName() ?? '',
            'representative_national_id' => $company->getRepresentativeNationalId() ?? '',
            'representative_role'       => $company->getRepresentativeRole() ?? '',
        ];

        /** @var \App\Entity\Teacher[] $selectedLiaisons */
        $selectedLiaisons = $company->getLiaisons()->toArray();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_company_' . $id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $rawContactInfo = $request->request->getString('contact_information');
            $values = [
                'name'                      => trim($request->request->getString('name')),
                'vat_number'                => trim($request->request->getString('vat_number')),
                'city'                      => trim($request->request->getString('city')),
                'contact_information'       => $this->contactSanitizer->sanitize($rawContactInfo),
                'exceptional_circumstances' => trim($request->request->getString('exceptional_circumstances')),
                'representative_first_name' => trim($request->request->getString('representative_first_name')),
                'representative_last_name'  => trim($request->request->getString('representative_last_name')),
                'representative_national_id' => trim($request->request->getString('representative_national_id')),
                'representative_role'       => trim($request->request->getString('representative_role')),
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
                    ->setContactInformation($values['contact_information'] !== '' ? $values['contact_information'] : null)
                    ->setExceptionalCircumstances($values['exceptional_circumstances'] !== '' ? $values['exceptional_circumstances'] : null)
                    ->setRepresentativeFirstName($values['representative_first_name'] !== '' ? $values['representative_first_name'] : null)
                    ->setRepresentativeLastName($values['representative_last_name'] !== '' ? $values['representative_last_name'] : null)
                    ->setRepresentativeNationalId($values['representative_national_id'] !== '' ? $values['representative_national_id'] : null)
                    ->setRepresentativeRole($values['representative_role'] !== '' ? $values['representative_role'] : null);

                foreach ($company->getLiaisons()->toArray() as $liaison) {
                    $company->removeLiaison($liaison);
                }
                foreach ($submittedIds as $teacherId) {
                    $teacher = $this->teachers->findById($teacherId);
                    if ($teacher !== null) {
                        $company->addLiaison($teacher);
                    }
                }

                $errors = $this->mapViolations($this->validator->validate($company));

                if (!empty($errors)) {
                    $selectedLiaisons = $company->getLiaisons()->toArray();
                } else {
                    $this->em->flush();

                    $this->addFlash('success', $this->t('company.flash.saved'));

                    return $this->redirectToRoute('app_companies_edit', ['id' => $id]);
                }
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

    #[Route('/{id}/historial', name: 'app_companies_history')]
    public function history(string $id): Response
    {
        $company = $this->requireCompanyInCurrentCentre($id);
        $this->denyAccessUnlessGranted(CompanyVoter::EDIT, $company);

        return $this->render('company/history.html.twig', [
            'company' => $company,
            'audits'  => $this->companyAudits->findByCompany($company),
        ]);
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
            $this->addFlash('success', $this->t('worker.flash.removed'));
        } else {
            $this->addFlash('error', $this->t('worker.flash.not_found'));
        }

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
     * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
     * @return array<string, string>
     */
    private function mapViolations(\Symfony\Component\Validator\ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        $pathMap = ['vatNumber' => 'vat_number'];
        foreach ($violations as $violation) {
            $path = $violation->getPropertyPath();
            $key  = $pathMap[$path] ?? $path;
            if (!isset($errors[$key])) {
                $errors[$key] = (string) $violation->getMessage();
            }
        }

        return $errors;
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

    private function getTempImportPath(string $importId): string
    {
        return $this->getParameter('kernel.project_dir') . '/var/tmp/company-imports/' . basename($importId) . '.xlsx';
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'companies');
    }
}
