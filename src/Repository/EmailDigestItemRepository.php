<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EducationalCentre;
use App\Entity\EmailDigestItem;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailDigestItem>
 */
class EmailDigestItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailDigestItem::class);
    }

    /**
     * Deja una línea a la espera del próximo resumen diario del destinatario. Inserta con la conexión DBAL
     * (sin flush del EntityManager): los avisos se encolan justo después del flush de la operación del usuario.
     *
     * @param array<string, scalar|null> $params parámetros de traducción de la línea (las claves incluyen los %)
     */
    public function enqueue(
        EducationalCentre $centre,
        Teacher $recipient,
        string $eventKey,
        array $params,
        ?string $url,
        \DateTimeImmutable $createdAt,
    ): void {
        $item = new EmailDigestItem($centre, $recipient, $eventKey, $params, $url, $createdAt);

        $this->getEntityManager()->getConnection()->insert(
            'email_digest_item',
            [
                'id'                    => $item->getId(),
                'educational_centre_id' => $item->getEducationalCentre()->getId(),
                'recipient_id'          => $item->getRecipient()->getId(),
                'event_key'             => $item->getEventKey(),
                'params'                => $item->getParams(),
                'url'                   => $item->getUrl(),
                'created_at'            => $item->getCreatedAt(),
            ],
            [
                'id'                    => 'uuid',
                'educational_centre_id' => 'uuid',
                'recipient_id'          => 'uuid',
                'params'                => 'json',
                'created_at'            => 'datetime_immutable',
            ],
        );
    }

    /**
     * Líneas aún sin enviar, de la más antigua a la más reciente, con destinatario y centro cargados.
     *
     * @return list<EmailDigestItem>
     */
    public function findUnsent(): array
    {
        /** @var list<EmailDigestItem> $result */
        $result = $this->createQueryBuilder('i')
            ->addSelect('r', 'c')
            ->join('i.recipient', 'r')
            ->join('i.educationalCentre', 'c')
            ->where('i.sentAt IS NULL')
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** Elimina las líneas enviadas antes de $sentBefore y las que nunca se enviaron y son anteriores a $unsentBefore. */
    public function purge(\DateTimeImmutable $sentBefore, \DateTimeImmutable $unsentBefore): int
    {
        $sent = (int) $this->createQueryBuilder('i')
            ->delete()
            ->where('i.sentAt IS NOT NULL AND i.sentAt < :sentBefore')
            ->setParameter('sentBefore', $sentBefore)
            ->getQuery()
            ->execute();

        $unsent = (int) $this->createQueryBuilder('i')
            ->delete()
            ->where('i.sentAt IS NULL AND i.createdAt < :unsentBefore')
            ->setParameter('unsentBefore', $unsentBefore)
            ->getQuery()
            ->execute();

        return $sent + $unsent;
    }
}
