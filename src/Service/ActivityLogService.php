<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Teacher;
use App\Message\ActivityLogMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class ActivityLogService
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
        private readonly TenantContext $tenantContext,
        #[Autowire(env: 'bool:APP_LOG')] private readonly bool $enabled,
    ) {}

    /**
     * Registra una acción en el log de actividad de forma asíncrona.
     * Nunca lanza excepciones: el log nunca debe interrumpir la acción principal.
     *
     * @param array<string, mixed>|null $data
     */
    public function log(string $actionType, ?array $data = null): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
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
                $originalUser = $token->getOriginalToken()->getUser();
                if ($originalUser instanceof Teacher) {
                    $realUser = $originalUser;
                }
            }

            $request = $this->requestStack->getCurrentRequest();
            $ip      = $request?->getClientIp() ?? '0.0.0.0';

            $academicYearId = null;
            $centre = $this->tenantContext->getSelectedCentre();
            if ($centre !== null) {
                $year           = $this->tenantContext->getViewYear($centre);
                $academicYearId = $year?->getId()?->toRfc4122();
            }

            $this->bus->dispatch(new ActivityLogMessage(
                createdAt:      new \DateTimeImmutable(),
                ip:             $ip,
                actionType:     $actionType,
                activeUserId:   $activeUser->getId()->toRfc4122(),
                realUserId:     $realUser?->getId()?->toRfc4122(),
                academicYearId: $academicYearId,
                data:           $data,
            ));
        } catch (\Throwable) {
            // El log nunca puede romper la funcionalidad principal
        }
    }
}
