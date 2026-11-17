<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Comprueba que el hub de Mercure (la sincronización en vivo de las estancias) está al otro lado: publica un aviso de prueba
 * en un canal propio, sin datos, que nadie escucha. Que el hub lo acepte demuestra a la vez que está en marcha, que la URL
 * interna (`MERCURE_URL`) es alcanzable y que la clave de publicación (`MERCURE_JWT_SECRET`) coincide con la suya. Lo que no
 * comprueba es la parte del navegador (la URL pública y la cookie de suscripción).
 *
 * Nunca lanza excepciones: el resultado dice qué ha pasado, para que la pantalla se pinte siempre.
 */
class MercureStatus
{
    /** Canal del aviso de prueba: ninguna pantalla se suscribe a él. */
    public const string TOPIC = 'system/ping';

    public const string OK         = 'ok';
    public const string FAILED     = 'failed';
    public const string NOT_CONFIG = 'not_configured';

    /** @param string|null $url la URL interna del hub (`MERCURE_URL`); vacía o ausente = sin hub configurado (p. ej. `php -S`) */
    public function __construct(
        private readonly HubInterface $hub,
        #[Autowire(env: 'default::MERCURE_URL')]
        private readonly ?string $url = null,
    ) {}

    /** @return array{state: string, url: string, public_url: string, error: string|null} */
    public function check(): array
    {
        $url = trim((string) $this->url);

        try {
            $publicUrl = $this->hub->getPublicUrl();
        } catch (\Throwable) {
            $publicUrl = '';
        }

        if ($url === '') {
            return ['state' => self::NOT_CONFIG, 'url' => '', 'public_url' => $publicUrl, 'error' => null];
        }

        try {
            $this->hub->publish(new Update(self::TOPIC, (string) json_encode(['ts' => time()]), true));
        } catch (\Throwable $e) {
            return ['state' => self::FAILED, 'url' => $url, 'public_url' => $publicUrl, 'error' => mb_substr($e->getMessage(), 0, 300)];
        }

        return ['state' => self::OK, 'url' => $url, 'public_url' => $publicUrl, 'error' => null];
    }
}
