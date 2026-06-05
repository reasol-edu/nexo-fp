<?php

declare(strict_types=1);

namespace App\Twig\Components\Company;

use App\Entity\Company;
use App\Entity\Worker;
use App\Security\Voter\CompanyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsLiveComponent]
class WorkerFormComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public Worker $worker;

    #[LiveProp]
    public Company $company;

    #[LiveProp(writable: true)]
    public string $firstName = '';

    #[LiveProp(writable: true)]
    public string $lastName = '';

    #[LiveProp(writable: true)]
    public string $workEmail = '';

    #[LiveProp(writable: true)]
    public string $workPhone = '';

    /** @var array<string, string> */
    #[LiveProp]
    public array $errors = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {}

    public function mount(Worker $worker, Company $company): void
    {
        $this->worker    = $worker;
        $this->company   = $company;
        $this->firstName = $worker->getName()->getFirstName();
        $this->lastName  = $worker->getName()->getLastName();
        $this->workEmail = $worker->getWorkEmail() ?? '';
        $this->workPhone = $worker->getWorkPhoneNumber() ?? '';
    }

    #[LiveAction]
    public function save(): ?Response
    {
        $this->denyAccessUnlessGranted(CompanyVoter::EDIT, $this->company);

        $this->errors = [];
        if (trim($this->firstName) === '') {
            $this->errors['firstName'] = $this->translator->trans('worker.error.first_name_required', [], 'companies');
        }
        if (trim($this->lastName) === '') {
            $this->errors['lastName'] = $this->translator->trans('worker.error.last_name_required', [], 'companies');
        }
        if (!empty($this->errors)) {
            return null;
        }

        $this->worker->getName()->setFirstName(trim($this->firstName))->setLastName(trim($this->lastName));
        $this->worker->setWorkEmail($this->workEmail !== '' ? trim($this->workEmail) : null);
        $this->worker->setWorkPhoneNumber($this->workPhone !== '' ? trim($this->workPhone) : null);
        $this->em->flush();
        $this->addFlash('success', $this->translator->trans('worker.flash.saved', [], 'companies'));

        return $this->redirectToRoute('app_companies_edit', [
            'id' => $this->company->getId()->toRfc4122(),
        ]);
    }
}
