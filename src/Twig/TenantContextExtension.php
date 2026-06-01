<?php

namespace App\Twig;

use App\Entity\EducationalCentre;
use App\Service\TenantContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TenantContextExtension extends AbstractExtension
{
    public function __construct(private readonly TenantContext $context) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('active_centre', $this->getActiveCentre(...)),
        ];
    }

    public function getActiveCentre(): ?EducationalCentre
    {
        return $this->context->getSelectedCentre();
    }
}
