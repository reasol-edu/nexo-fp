<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\PersonName;
use PHPUnit\Framework\TestCase;

class PersonNameTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $name = new PersonName('María', 'García López');

        self::assertSame('María', $name->getFirstName());
        self::assertSame('García López', $name->getLastName());
    }

    public function testFullConcatenatesWithSpace(): void
    {
        $name = new PersonName('Ana', 'Martínez');

        self::assertSame('Ana Martínez', $name->full());
    }

    public function testSettersOverrideValues(): void
    {
        $name = new PersonName('Juan', 'Pérez');
        $name->setFirstName('Carlos')->setLastName('Ruiz');

        self::assertSame('Carlos', $name->getFirstName());
        self::assertSame('Ruiz', $name->getLastName());
        self::assertSame('Carlos Ruiz', $name->full());
    }

    public function testFullWithEmptyFirstName(): void
    {
        $name = new PersonName('', 'Sánchez');

        self::assertSame(' Sánchez', $name->full());
    }
}
