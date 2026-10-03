<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Indica si una contraseña figura en filtraciones de datos conocidas, mediante la API de rangos de
 * «Have I Been Pwned» con k-anonimato: del servidor solo salen los 5 primeros caracteres del SHA-1 de
 * la contraseña y la coincidencia se busca en local entre los sufijos devueltos (con relleno, así que
 * ni siquiera el tamaño de la respuesta revela nada).
 *
 * No se usa la restricción NotCompromisedPassword de Symfony porque su validador no fija un tiempo
 * máximo: en una red que descarta en silencio el tráfico saliente (la intranet aislada de un centro),
 * cambiar la contraseña se quedaría colgado un minuto. Aquí la petición se limita a unos segundos y
 * cualquier fallo deja pasar la contraseña (queda registrado): es una red de seguridad adicional,
 * nunca un motivo para dejar a nadie sin poder entrar.
 *
 * Opcional: consulta un servicio de terceros, así que está desactivado salvo que la administración
 * ponga APP_PASSWORD_BREACH_CHECK=true (false por defecto en .env, y siempre en los tests).
 */
final class CompromisedPasswordChecker
{
    private const string ENDPOINT = 'https://api.pwnedpasswords.com/range/%s';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'bool:APP_PASSWORD_BREACH_CHECK')]
        private readonly bool $enabled,
    ) {}

    public function isCompromised(string $password): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $hash   = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        try {
            $body = $this->httpClient->request('GET', \sprintf(self::ENDPOINT, $prefix), [
                'headers'      => ['Add-Padding' => 'true'],
                'timeout'      => 3,
                'max_duration' => 5,
            ])->getContent();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('No se ha podido comprobar la contraseña frente a filtraciones conocidas: {error}', ['error' => $e->getMessage()]);

            return false;
        }

        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            [$candidate, $count] = array_pad(explode(':', trim($line), 2), 2, '0');
            if ($candidate === $suffix && (int) $count > 0) {
                return true;
            }
        }

        return false;
    }
}
