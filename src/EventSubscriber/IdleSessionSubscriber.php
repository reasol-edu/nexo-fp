<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Teacher;
use App\Service\AppSettingsInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Cierra la sesión de un docente tras un periodo sin ninguna petición (ajuste global
 * security.idle_timeout_minutes; 0 lo desactiva): un ordenador de sala de profesores con la sesión
 * abierta no debe quedar utilizable por quien se siente después. Cualquier petición cuenta como
 * actividad, también las actualizaciones de los componentes Live.
 * El cierre pasa por Security::logout(), así que se registra como cualquier otro, y la pantalla de
 * inicio de sesión explica el motivo («?sesion=caducada»).
 */
final class IdleSessionSubscriber implements EventSubscriberInterface
{
    public const SETTING = 'security.idle_timeout_minutes';

    private const SESSION_KEY = 'security.last_activity';

    /** Si el ajuste no está definido (p. ej. aún sin migrar). */
    private const DEFAULT_MINUTES = 120;

    public function __construct(
        private readonly Security $security,
        private readonly AppSettingsInterface $settings,
        private readonly ClockInterface $clock,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Después del cortafuegos (prioridad 8), que ya ha autenticado la petición.
        return [KernelEvents::REQUEST => ['onKernelRequest', 6]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasSession() || !$this->security->getUser() instanceof Teacher) {
            return;
        }

        $session = $request->getSession();
        $now     = $this->clock->now()->getTimestamp();
        $last    = $session->get(self::SESSION_KEY);
        $minutes = $this->timeoutMinutes();

        if ($minutes > 0 && \is_int($last) && $now - $last > $minutes * 60) {
            $this->security->logout(false);
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login', ['sesion' => 'caducada'])));

            return;
        }

        $session->set(self::SESSION_KEY, $now);
    }

    private function timeoutMinutes(): int
    {
        $minutes = $this->settings->getGlobal(self::SETTING);

        return \is_int($minutes) && $minutes >= 0 ? $minutes : self::DEFAULT_MINUTES;
    }
}
