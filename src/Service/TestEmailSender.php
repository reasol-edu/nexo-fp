<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Envía un correo de prueba de una línea para que un administrador compruebe que el transporte de correo está bien configurado.
 * De forma síncrona y directa al transporte (no por el bus de mensajes, que solo lo encolaría): un fallo vuelve al instante, con
 * el mensaje del propio transporte. Solo a la dirección indicada, que quien llama toma del administrador con sesión iniciada.
 */
class TestEmailSender
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly TranslatorInterface $translator,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $fromAddress,
        #[Autowire('%app.name%')]
        private readonly string $appName,
    ) {}

    /** @return string|null el motivo del fallo, o null si el transporte lo aceptó */
    public function send(string $to): ?string
    {
        $email = (new Email())
            ->from(new Address($this->fromAddress, $this->appName))
            ->to($to)
            ->subject($this->translator->trans('emails.test.subject', ['%app%' => $this->appName], 'emails'))
            ->text($this->translator->trans('emails.test.body', ['%app%' => $this->appName], 'emails'));

        try {
            $this->transport->send($email);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }
}
