<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Los mensajes con los que el worker se rindió tras sus reintentos (el transporte `failed`): correos que no salieron, un
 * recordatorio, una purga. Se listan para que un administrador los vea, y se reintentan o descartan uno a uno.
 *
 * Reintentar envía el mensaje al transporte `async` (donde lo recoge el worker) y lo quita de `failed`: nunca se despacha por
 * el bus durante la petición web, porque los mensajes sin enrutar (los programados) se ejecutarían dentro de la petición HTTP.
 */
class FailedMessages
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.failed')]
        private readonly TransportInterface $failed,
        #[Autowire(service: 'messenger.transport.async')]
        private readonly TransportInterface $async,
    ) {}

    /** Cuántos hay, o null si el transporte no lo puede decir (una tabla inaccesible). */
    public function count(): ?int
    {
        if (!$this->failed instanceof MessageCountAwareInterface) {
            return null;
        }

        try {
            return $this->failed->getMessageCount();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{id: string, message: string, error: string, at: \DateTimeImmutable|null}>|null null si no se pueden leer
     */
    public function list(int $limit = 20): ?array
    {
        if (!$this->failed instanceof ListableReceiverInterface) {
            return null;
        }

        try {
            $rows = [];
            foreach ($this->failed->all($limit) as $envelope) {
                $id = $envelope->last(TransportMessageIdStamp::class)?->getId();
                if (!\is_int($id) && !\is_string($id)) {
                    continue;
                }

                $redelivered = $envelope->last(RedeliveryStamp::class)?->getRedeliveredAt();

                $rows[] = [
                    'id'      => (string) $id,
                    'message' => (new \ReflectionClass($envelope->getMessage()))->getShortName(),
                    'error'   => mb_strimwidth($envelope->last(ErrorDetailsStamp::class)?->getExceptionMessage() ?? '', 0, 300, '…'),
                    'at'      => $redelivered === null ? null : \DateTimeImmutable::createFromInterface($redelivered),
                ];
            }

            return $rows;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Devuelve el mensaje a la cola que consume el worker y lo quita de los fallidos. Devuelve su clase, o null si ya no existe. */
    public function retry(string $id): ?string
    {
        $envelope = $this->find($id);
        if ($envelope === null) {
            return null;
        }

        $this->async->send(new Envelope($envelope->getMessage()));
        $this->failed->reject($envelope);

        return (new \ReflectionClass($envelope->getMessage()))->getShortName();
    }

    /** Descarta el mensaje para siempre. Devuelve su clase, o null si ya no existe. */
    public function discard(string $id): ?string
    {
        $envelope = $this->find($id);
        if ($envelope === null) {
            return null;
        }

        $this->failed->reject($envelope);

        return (new \ReflectionClass($envelope->getMessage()))->getShortName();
    }

    private function find(string $id): ?Envelope
    {
        if (!$this->failed instanceof ListableReceiverInterface) {
            return null;
        }

        try {
            return $this->failed->find($id);
        } catch (\Throwable) {
            return null;
        }
    }
}
