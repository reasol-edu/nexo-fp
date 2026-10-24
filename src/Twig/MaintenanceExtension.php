<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\MaintenanceMode;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** `maintenance_active()`: permite al diseño y a la pantalla de acceso avisar de que la aplicación está en mantenimiento. */
final class MaintenanceExtension extends AbstractExtension
{
    public function __construct(
        private readonly MaintenanceMode $maintenance,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('maintenance_active', $this->maintenance->isActive(...)),
        ];
    }
}
