<?php

namespace App\EventSubscriber;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Runs pending Doctrine migrations automatically on the first HTTP request of
 * each worker process when the app is running as a self-contained embedded
 * binary (NEXO_EMBEDDED=1).  The static flag ensures this runs at most once
 * per PHP worker lifetime, not on every request.
 */
final class AutoMigrateSubscriber implements EventSubscriberInterface
{
    private static bool $done = false;

    public function __construct(private readonly DependencyFactory $migrations) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 2048]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (self::$done || !$event->isMainRequest() || '1' !== getenv('NEXO_EMBEDDED')) {
            return;
        }
        self::$done = true;

        try {
            $resolver = $this->migrations->getVersionAliasResolver();
            $plan = $this->migrations->getMigrationPlanCalculator()
                ->getPlanUntilVersion($resolver->resolveVersionAlias('latest'));

            if (\count($plan) > 0) {
                $this->migrations->getMigrator()->migrate($plan, new MigratorConfiguration());
            }
        } catch (\Throwable) {
            self::$done = false;
        }
    }
}
