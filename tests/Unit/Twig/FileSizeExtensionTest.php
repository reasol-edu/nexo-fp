<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\FileSizeExtension;
use PHPUnit\Framework\TestCase;

final class FileSizeExtensionTest extends TestCase
{
    public function testSizesAreHumanReadable(): void
    {
        $extension = new FileSizeExtension();

        self::assertSame('0 B', $extension->formatFileSize(0));
        self::assertSame('512 B', $extension->formatFileSize(512));
        self::assertSame('1,0 KiB', $extension->formatFileSize(1024));
        self::assertSame('1,5 MiB', $extension->formatFileSize(1572864));
        self::assertSame('2,0 GiB', $extension->formatFileSize(2 * 1024 ** 3));
        self::assertSame('2.048,0 GiB', $extension->formatFileSize(2 * 1024 ** 4));
    }
}
