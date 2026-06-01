<?php

namespace App\Twig;

use App\Entity\EducationalCentre;
use App\Entity\Teacher;
use App\Service\TenantContext;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TenantContextExtension extends AbstractExtension
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly Security $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('active_centre', $this->getActiveCentre(...)),
            new TwigFunction('can_switch_centre', $this->canSwitchCentre(...)),
        ];
    }

    public function getActiveCentre(): ?EducationalCentre
    {
        return $this->context->getSelectedCentre();
    }

    public function canSwitchCentre(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof Teacher) {
            return false;
        }

        return $this->context->canSwitchCentre($user);
    }
}
