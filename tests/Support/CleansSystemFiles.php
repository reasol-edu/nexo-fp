<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Las carpetas de copias y de estado del sistema de los tests (ver config/services_test.yaml) viven en la caché del kernel y se
 * comparten entre tests: quien las toca las vacía antes y después para que ningún test vea lo que dejó otro.
 *
 * El test debe tener el contenedor arrancado (`self::getContainer()`).
 */
trait CleansSystemFiles
{
    protected function cleanSystemFiles(): void
    {
        foreach (['app.backup_dir', 'app.state_dir'] as $parameter) {
            $dir = self::getContainer()->getParameter($parameter);
            if (\is_string($dir)) {
                $this->removeDirectory($dir);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (is_file($dir) || is_link($dir)) {
            @unlink($dir);

            return;
        }
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeDirectory($dir . '/' . $entry);
            }
        }
        @rmdir($dir);
    }
}
