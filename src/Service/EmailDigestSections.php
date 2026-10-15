<?php

declare(strict_types=1);

namespace App\Service;

/** Secciones del resumen diario por correo y sección a la que pertenece cada evento. */
final class EmailDigestSections
{
    /** Orden de aparición. */
    public const ORDER = ['assignments', 'positions', 'signatures'];

    /** @var array<string, string> */
    private const BY_EVENT = [
        'tutor_assigned'         => 'assignments',
        'shared_position_taken'  => 'assignments',
        'shared_position_removed' => 'assignments',
        'positions_created'      => 'positions',
        'signature_reminder'     => 'signatures',
    ];

    public static function forEvent(string $eventKey): string
    {
        return self::BY_EVENT[$eventKey] ?? 'assignments';
    }
}
