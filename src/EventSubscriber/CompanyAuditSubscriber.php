<?php

namespace App\EventSubscriber;

use App\Entity\Company;
use App\Entity\CompanyAudit;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class CompanyAuditSubscriber
{
    /** @var list<CompanyAudit> */
    private array $pending = [];

    public function __construct(
        private readonly Security $security,
    ) {}

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Company) {
            return;
        }

        $changeset = $args->getObjectManager()
            ->getUnitOfWork()
            ->getEntityChangeSet($entity);

        if (empty($changeset)) {
            return;
        }

        /** @var array<string, array{old: scalar|null, new: scalar|null}> $changes */
        $changes = [];
        foreach ($changeset as $field => $diff) {
            $changes[$field] = [
                'old' => $this->toScalar(is_array($diff) ? $diff[0] : null),
                'new' => $this->toScalar(is_array($diff) ? $diff[1] : null),
            ];
        }

        $user = $this->security->getUser();

        $this->pending[] = new CompanyAudit(
            $entity,
            $user instanceof Teacher ? $user : null,
            $changes,
        );
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->pending)) {
            return;
        }

        $em = $args->getObjectManager();
        $audits = $this->pending;
        $this->pending = [];

        foreach ($audits as $audit) {
            $em->persist($audit);
        }

        $em->flush();
    }

    private function toScalar(mixed $value): string|int|float|bool|null
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        return null;
    }
}
