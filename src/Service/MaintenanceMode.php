<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Indica si toda la aplicación está en modo mantenimiento (ajuste global maintenance.enabled): solo
 * los administradores globales pueden usarla entonces; véase MaintenanceModeSubscriber. Está
 * desactivado si el ajuste no está definido (p. ej. una base de datos aún sin migrar), de modo que
 * una definición ausente nunca deja a nadie fuera.
 */
final class MaintenanceMode
{
    public const string SETTING = 'maintenance.enabled';

    public function __construct(
        private readonly AppSettingsInterface $settings,
    ) {}

    public function isActive(): bool
    {
        return $this->settings->getGlobal(self::SETTING) === true;
    }
}
