<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * En producción los iconos son solo locales (`iconify: false` + `ignore_not_found`): un icono usado en una
 * plantilla pero no importado desaparece en silencio. Evita olvidar `php bin/console ux:icons:import`.
 */
class IconsImportedTest extends TestCase
{
    public function testEveryIconReferencedInTemplatesAndCodeIsImportedLocally(): void
    {
        $root    = dirname(__DIR__, 2);
        $missing = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$root/templates", \FilesystemIterator::SKIP_DOTS));
        $paths = [];
        foreach ($files as $file) {
            $paths[] = $file->getPathname();
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$root/src", \FilesystemIterator::SKIP_DOTS)) as $file) {
            $paths[] = $file->getPathname();
        }

        foreach ($paths as $path) {
            if (!preg_match('/\.(twig|php)$/', $path)) {
                continue;
            }
            preg_match_all('/\b(heroicons):([a-z0-9-]+)/', (string) file_get_contents($path), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $set, $name]) {
                if (!is_file("$root/assets/icons/$set/$name.svg")) {
                    $missing["$set:$name"] = true;
                }
            }
        }

        self::assertSame([], array_keys($missing), 'Iconos sin importar: ejecuta «php bin/console ux:icons:import <icono>».');
    }
}
