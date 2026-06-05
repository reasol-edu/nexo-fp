<?php

use App\Kernel;

// Early-init for standalone embedded deployments.
// NEXO_EMBEDDED=1 is injected via the embedded Caddyfile's global `env` block,
// so this code is a no-op in Docker and development contexts.
if ('1' === getenv('NEXO_EMBEDDED')) {
    $dataDir = rtrim((string) getcwd(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'data';
    @mkdir($dataDir, 0755, true);

    $dbPath = str_replace('\\', '/', $dataDir) . '/nexo-fp.db';
    putenv("DATABASE_URL=sqlite:///{$dbPath}");
    $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = "sqlite:///{$dbPath}";

    $secretFile = $dataDir . DIRECTORY_SEPARATOR . '.secret';
    if (!file_exists($secretFile)) {
        file_put_contents($secretFile, bin2hex(random_bytes(32)));
    }
    $secret = trim((string) file_get_contents($secretFile));
    putenv("APP_SECRET={$secret}");
    $_ENV['APP_SECRET'] = $_SERVER['APP_SECRET'] = $secret;

    $port = ltrim(getenv('SERVER_ADDR') ?: ':8180', ':') ?: '8180';
    putenv("DEFAULT_URI=http://localhost:{$port}");
    $_ENV['DEFAULT_URI'] = $_SERVER['DEFAULT_URI'] = "http://localhost:{$port}";
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
