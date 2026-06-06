<?php

namespace App\EventSubscriber;

use App\Entity\Teacher;
use App\Repository\EducationalCentreRepository;
use App\Service\TenantContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TenantContextSubscriber implements EventSubscriberInterface
{
    private const EXCLUDED_ROUTES = [
        'app_login',
        'app_logout',
        'app_select_centre',
        'app_select_centre_choose',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EducationalCentreRepository $centres,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 4]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');

        if (\in_array($route, self::EXCLUDED_ROUTES, strict: true)) {
            return;
        }

        // Admin section does not require centre selection
        if (\is_string($route) && \str_starts_with($route, 'app_admin')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof Teacher) {
            return;
        }

        if ($this->tenantContext->isSelected()) {
            // Guard against stale sessions where the stored UUID no longer resolves
            if ($this->tenantContext->getSelectedCentre() === null) {
                $this->tenantContext->clear();
            } else {
                return;
            }
        }

        $centres = $this->centres->findAccessibleByTeacher($user);

        if (\count($centres) === 1) {
            $this->tenantContext->selectCentre($centres[0]);
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_select_centre')
        ));
    }
}
