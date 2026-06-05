<?php

declare(strict_types=1);

namespace App\Twig\Components\Company;

use App\Entity\Workcenter;
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
class WorkcenterFormComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public Workcenter $workcenter;

    #[LiveProp(writable: true)]
    public string $name = '';

    #[LiveProp(writable: true)]
    public string $city = '';

    /** @var array<string, string> */
    #[LiveProp]
    public array $errors = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {}

    public function mount(Workcenter $workcenter): void
    {
        $this->workcenter = $workcenter;
        $this->name = $workcenter->getName();
        $this->city = $workcenter->getCity();
    }

    #[LiveAction]
    public function save(): ?Response
    {
        $this->denyAccessUnlessGranted(CompanyVoter::EDIT, $this->workcenter->getCompany());

        $this->errors = [];
        if (trim($this->name) === '') {
            $this->errors['name'] = $this->translator->trans('workcenter.error.name_required', [], 'companies');
        }
        if (trim($this->city) === '') {
            $this->errors['city'] = $this->translator->trans('workcenter.error.city_required', [], 'companies');
        }
        if (!empty($this->errors)) {
            return null;
        }

        $this->workcenter->setName(trim($this->name))->setCity(trim($this->city));
        $this->em->flush();
        $this->addFlash('success', $this->translator->trans('workcenter.flash.saved', [], 'companies'));

        return $this->redirectToRoute('app_companies_edit', [
            'id' => $this->workcenter->getCompany()->getId()->toRfc4122(),
        ]);
    }
}
