<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testRejectsPasswordShorterThanMinLength(): void
    {
        $policy = new PasswordPolicy();
        self::assertSame('profile.error.password_too_short', $policy->firstViolationKey('short'));
        self::assertSame('profile.error.password_too_short', $policy->firstViolationKey(str_repeat('a', PasswordPolicy::MIN_LENGTH - 1)));
    }

    public function testAcceptsPasswordAtMinLength(): void
    {
        $policy = new PasswordPolicy();
        self::assertNull($policy->firstViolationKey(str_repeat('a', PasswordPolicy::MIN_LENGTH)));
    }

    public function testAcceptsLongerPassword(): void
    {
        $policy = new PasswordPolicy();
        self::assertNull($policy->firstViolationKey('una-contraseña-larga-2026'));
    }

    public function testCountsMultibyteCharacters(): void
    {
        // 12 caracteres con acentos y ñ — debe pasar aunque algunos ocupen 2 bytes en UTF-8.
        $policy = new PasswordPolicy();
        self::assertNull($policy->firstViolationKey('niño-acción1'));
    }
}
