<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Teacher;
use App\Message\ActivityLogMessage;
use App\Service\TenantContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;

final class ActivityLogSubscriber
{
    /** Rutas del profiler y del debug toolbar que nunca se registran */
    private const EXCLUDED_ROUTE_PREFIXES = ['_profiler', '_wdt', '_preview_error'];

    /** Rutas GET que exponen datos y merecen registro aunque sean solo lectura */
    private const SENSITIVE_ROUTE_PATTERNS = ['exportar', 'informe', 'report', 'download'];

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TenantContext $tenantContext,
        #[Autowire(env: 'bool:APP_LOG')] private readonly bool $enabled,
    ) {}

    // ── Peticiones HTTP ───────────────────────────────────────────────────────

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function onTerminate(TerminateEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        try {
            $request = $event->getRequest();
            $route   = (string) ($request->attributes->get('_route') ?? '');

            foreach (self::EXCLUDED_ROUTE_PREFIXES as $prefix) {
                if (str_starts_with($route, $prefix)) {
                    return;
                }
            }

            $method         = $request->getMethod();
            $isWrite        = \in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], strict: true);
            $isSensitiveGet = !$isWrite && $this->isSensitiveRoute($route);

            if (!$isWrite && !$isSensitiveGet) {
                return;
            }

            $this->dispatchFromRequest($request, $route, [
                'method' => $method,
                'path'   => $request->getPathInfo(),
                'status' => $event->getResponse()->getStatusCode(),
            ]);
        } catch (\Throwable) {
            // El log nunca puede romper la funcionalidad principal
        }
    }

    // ── Eventos de sesión ─────────────────────────────────────────────────────

    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $user = $event->getAuthenticatedToken()->getUser();
        if (!$user instanceof Teacher) {
            return;
        }

        $this->dispatchSessionEvent(
            'session.login',
            $event->getRequest(),
            $user,
            null,
        );
    }

    #[AsEventListener]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->dispatchAnonymousEvent(
            'session.login_failed',
            $event->getRequest(),
            ['username' => $event->getPassport()?->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class)?->getUserIdentifier()],
        );
    }

    #[AsEventListener]
    public function onLogout(LogoutEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $token = $event->getToken();
        $user  = $token?->getUser();
        if (!$user instanceof Teacher) {
            return;
        }

        $this->dispatchSessionEvent('session.logout', $event->getRequest(), $user, null);
    }

    #[AsEventListener]
    public function onSwitchUser(SwitchUserEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $request    = $event->getRequest();
        $token      = $this->tokenStorage->getToken();
        $targetUser = $event->getTargetUser();

        // Si el username objetivo es _exit, se está abandonando la suplantación
        $username  = $request->query->getString('_switch_user', '');
        $isExiting = $username === SwitchUserListener::EXIT_VALUE;

        if ($isExiting) {
            // El token actual todavía es el SwitchUserToken del usuario suplantado
            $activeUser = $token instanceof SwitchUserToken ? $token->getUser() : null;
            $realUser   = $token instanceof SwitchUserToken
                ? $token->getOriginalToken()->getUser()
                : ($targetUser instanceof Teacher ? $targetUser : null);
        } else {
            $activeUser = $targetUser instanceof Teacher ? $targetUser : null;
            $realUser   = $token?->getUser() instanceof Teacher ? $token->getUser() : null;
        }

        $actionType = $isExiting ? 'session.impersonate_stop' : 'session.impersonate_start';

        $this->bus->dispatch(new ActivityLogMessage(
            createdAt:      new \DateTimeImmutable(),
            ip:             $request->getClientIp() ?? '0.0.0.0',
            actionType:     $actionType,
            activeUserId:   $activeUser instanceof Teacher ? $activeUser->getId()->toRfc4122() : null,
            realUserId:     $realUser instanceof Teacher   ? $realUser->getId()->toRfc4122()   : null,
            academicYearId: null,
            data:           null,
        ));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @param array<string, mixed>|null $data */
    private function dispatchFromRequest(Request $request, string $actionType, ?array $data): void
    {
        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $activeUser = $token->getUser();
        if (!$activeUser instanceof Teacher) {
            return;
        }

        $realUser = null;
        if ($token instanceof SwitchUserToken) {
            $orig = $token->getOriginalToken()->getUser();
            if ($orig instanceof Teacher) {
                $realUser = $orig;
            }
        }

        $academicYearId = null;
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre !== null) {
            $year           = $this->tenantContext->getViewYear($centre);
            $academicYearId = $year?->getId()?->toRfc4122();
        }

        $this->bus->dispatch(new ActivityLogMessage(
            createdAt:      new \DateTimeImmutable(),
            ip:             $request->getClientIp() ?? '0.0.0.0',
            actionType:     $actionType,
            activeUserId:   $activeUser->getId()->toRfc4122(),
            realUserId:     $realUser?->getId()?->toRfc4122(),
            academicYearId: $academicYearId,
            data:           $data,
        ));
    }

    private function dispatchSessionEvent(string $actionType, Request $request, Teacher $activeUser, ?Teacher $realUser): void
    {
        $this->bus->dispatch(new ActivityLogMessage(
            createdAt:      new \DateTimeImmutable(),
            ip:             $request->getClientIp() ?? '0.0.0.0',
            actionType:     $actionType,
            activeUserId:   $activeUser->getId()->toRfc4122(),
            realUserId:     $realUser?->getId()?->toRfc4122(),
            academicYearId: null,
            data:           null,
        ));
    }

    /** @param array<string, mixed>|null $data */
    private function dispatchAnonymousEvent(string $actionType, Request $request, ?array $data): void
    {
        $this->bus->dispatch(new ActivityLogMessage(
            createdAt:      new \DateTimeImmutable(),
            ip:             $request->getClientIp() ?? '0.0.0.0',
            actionType:     $actionType,
            activeUserId:   null,
            realUserId:     null,
            academicYearId: null,
            data:           $data,
        ));
    }

    private function isSensitiveRoute(string $route): bool
    {
        foreach (self::SENSITIVE_ROUTE_PATTERNS as $pattern) {
            if (str_contains($route, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
