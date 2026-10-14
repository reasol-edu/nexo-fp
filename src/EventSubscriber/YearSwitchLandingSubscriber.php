<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Al cambiar de curso académico se vuelve a la página en la que se estaba, pero esa página puede no
 * existir en el otro curso (una estancia que no es de él, 404) o no estar permitida (403). Si la primera
 * petición tras el cambio acaba así, se lleva a la persona al Inicio con un aviso en lugar de mostrarle
 * el error.
 *
 * El controlador de cambio de curso deja la marca en la sesión (`markSwitched()`); esta clase la
 * consume en la siguiente petición principal.
 */
final class YearSwitchLandingSubscriber implements EventSubscriberInterface
{
    private const SESSION_KEY = 'year_switch_landing';
    private const ATTRIBUTE   = '_year_switch_landing';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {}

    /** Llamado por el controlador justo antes de redirigir tras cambiar de curso. */
    public static function markSwitched(Request $request): void
    {
        if ($request->hasSession()) {
            $request->getSession()->set(self::SESSION_KEY, true);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Antes que el cortafuegos (8), que ya puede denegar el acceso en esta misma petición,
            // y después de que la sesión esté disponible (128).
            KernelEvents::REQUEST   => ['onKernelRequest', 9],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasSession() || !$request->getSession()->has(self::SESSION_KEY)) {
            return;
        }

        // La marca vale para una sola petición: la que sigue al cambio de curso.
        $request->getSession()->remove(self::SESSION_KEY);
        $request->attributes->set(self::ATTRIBUTE, true);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request   = $event->getRequest();
        $exception = $event->getThrowable();

        if (!$event->isMainRequest()
            || $request->attributes->get(self::ATTRIBUTE) !== true
            || !$exception instanceof HttpExceptionInterface
            || !\in_array($exception->getStatusCode(), [403, 404], true)
        ) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', $this->translator->trans('year.switch.unavailable', [], 'messages'));
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_dashboard')));
    }
}
