<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Stay;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publica un aviso de cambio en una estancia a través de Mercure. El mensaje no
 * transporta datos de negocio: cada cliente vuelve a renderizar el Live Component
 * con sus propios permisos, de modo que nunca viajan HTML ni datos por el canal.
 */
final class StayRealtimeNotifier
{
    /**
     * @param bool $private Si el aviso se publica como update privado. En producción
     *                      es «true»: el suscriptor debe estar autorizado para el topic
     *                      mediante la cookie JWT que añade StayController::show(). En
     *                      desarrollo con «symfony serve» el hub está en otro origen
     *                      (la cookie de mismo origen no se envía) y se abre anónimo,
     *                      así que un suscriptor anónimo solo recibe updates públicos;
     *                      por eso en dev se publica como público. Es seguro: el canal
     *                      solo transporta el UUID de la estancia, nunca datos.
     */
    public function __construct(
        private readonly HubInterface $hub,
        private readonly bool $private = true,
    ) {}

    public function publicUrl(): string
    {
        return $this->hub->getPublicUrl();
    }

    public function topicForStay(Stay|string $stay): string
    {
        $id = $stay instanceof Stay ? $stay->getId()->toRfc4122() : $stay;

        return 'stay/' . $id;
    }

    public function publishStayChanged(Stay $stay): void
    {
        try {
            $this->hub->publish(new Update(
                $this->topicForStay($stay),
                (string) json_encode(['ts' => time()]),
                $this->private,
            ));
        } catch (\Throwable) {
            // Sin hub disponible (p. ej. servidor php -S o hub caído): degradamos
            // con elegancia. La pérdida de un aviso solo significa que alguna
            // pantalla no se refresca sola hasta la siguiente interacción.
        }
    }
}
