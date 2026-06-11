<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\SettingDefinition;
use App\Entity\SettingType;
use PHPUnit\Framework\TestCase;

class SettingDefinitionTest extends TestCase
{
    // ── Integer — range validation ────────────────────────────────────────────

    public function testIntegerWithinRangeIsValid(): void
    {
        $def = $this->makeIntDef(min: 5, max: 100);

        self::assertTrue($def->isValueValid('20'));
        self::assertTrue($def->isValueValid('5'));
        self::assertTrue($def->isValueValid('100'));
    }

    public function testIntegerBelowMinIsInvalid(): void
    {
        $def = $this->makeIntDef(min: 5, max: 100);

        self::assertFalse($def->isValueValid('4'));
        self::assertFalse($def->isValueValid('0'));
        self::assertFalse($def->isValueValid('-1'));
    }

    public function testIntegerAboveMaxIsInvalid(): void
    {
        $def = $this->makeIntDef(min: 5, max: 100);

        self::assertFalse($def->isValueValid('101'));
        self::assertFalse($def->isValueValid('999'));
    }

    public function testIntegerWithNoLimitsIsValid(): void
    {
        $def = $this->makeIntDef(min: null, max: null);

        self::assertTrue($def->isValueValid('0'));
        self::assertTrue($def->isValueValid('-100'));
        self::assertTrue($def->isValueValid('99999'));
    }

    public function testIntegerOnlyMinEnforcesLowerBound(): void
    {
        $def = $this->makeIntDef(min: 1, max: null);

        self::assertTrue($def->isValueValid('1'));
        self::assertTrue($def->isValueValid('100'));
        self::assertFalse($def->isValueValid('0'));
    }

    public function testIntegerOnlyMaxEnforcesUpperBound(): void
    {
        $def = $this->makeIntDef(min: null, max: 50);

        self::assertTrue($def->isValueValid('50'));
        self::assertTrue($def->isValueValid('0'));
        self::assertFalse($def->isValueValid('51'));
    }

    public function testIntegerNonNumericIsInvalid(): void
    {
        $def = $this->makeIntDef(min: null, max: null);

        self::assertFalse($def->isValueValid('abc'));
        self::assertFalse($def->isValueValid(''));
        self::assertFalse($def->isValueValid(' '));
    }

    public function testIntegerFloatIsInvalid(): void
    {
        $def = $this->makeIntDef(min: null, max: null);

        self::assertFalse($def->isValueValid('3.14'));
        self::assertFalse($def->isValueValid('1.0'));
    }

    // ── String — length validation ────────────────────────────────────────────

    public function testStringLengthInRangeIsValid(): void
    {
        $def = $this->makeStringDef(min: 2, max: 10);

        self::assertTrue($def->isValueValid('hi'));
        self::assertTrue($def->isValueValid('abcde'));
        self::assertTrue($def->isValueValid('1234567890'));
    }

    public function testEmptyStringIsAlwaysValid(): void
    {
        $def = $this->makeStringDef(min: 2, max: 10);

        self::assertTrue($def->isValueValid(''));
    }

    public function testStringTooLongIsInvalid(): void
    {
        $def = $this->makeStringDef(min: null, max: 5);

        self::assertFalse($def->isValueValid('toolong'));
        self::assertFalse($def->isValueValid('123456'));
    }

    public function testStringTooShortIsInvalid(): void
    {
        $def = $this->makeStringDef(min: 5, max: null);

        self::assertFalse($def->isValueValid('hi'));
        self::assertFalse($def->isValueValid('abc'));
    }

    public function testStringWithNoLimitsIsValid(): void
    {
        $def = $this->makeStringDef(min: null, max: null);

        self::assertTrue($def->isValueValid('any length whatsoever'));
        self::assertTrue($def->isValueValid(''));
    }

    // ── Boolean ───────────────────────────────────────────────────────────────

    public function testBooleanTrueIsValid(): void
    {
        $def = $this->makeBoolDef();

        self::assertTrue($def->isValueValid('true'));
    }

    public function testBooleanFalseIsValid(): void
    {
        $def = $this->makeBoolDef();

        self::assertTrue($def->isValueValid('false'));
    }

    public function testBooleanInvalidValueIsInvalid(): void
    {
        $def = $this->makeBoolDef();

        self::assertFalse($def->isValueValid('yes'));
        self::assertFalse($def->isValueValid('1'));
        self::assertFalse($def->isValueValid(''));
    }

    // ── min/max getters and setters ───────────────────────────────────────────

    public function testMinMaxDefaultToNull(): void
    {
        $def = (new SettingDefinition())->setKey('k')->setType(SettingType::Integer)->setDefaultValue('0');

        self::assertNull($def->getMinValue());
        self::assertNull($def->getMaxValue());
    }

    public function testSetMinMaxStoresValues(): void
    {
        $def = (new SettingDefinition())
            ->setKey('k')->setType(SettingType::Integer)->setDefaultValue('0')
            ->setMinValue(5)->setMaxValue(100);

        self::assertSame(5, $def->getMinValue());
        self::assertSame(100, $def->getMaxValue());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeIntDef(?int $min, ?int $max): SettingDefinition
    {
        return (new SettingDefinition())
            ->setKey('page.size')
            ->setType(SettingType::Integer)
            ->setDefaultValue('20')
            ->setMinValue($min)
            ->setMaxValue($max);
    }

    private function makeStringDef(?int $min, ?int $max): SettingDefinition
    {
        return (new SettingDefinition())
            ->setKey('some.string')
            ->setType(SettingType::String)
            ->setDefaultValue('')
            ->setMinValue($min)
            ->setMaxValue($max);
    }

    private function makeBoolDef(): SettingDefinition
    {
        return (new SettingDefinition())
            ->setKey('email.notifications')
            ->setType(SettingType::Boolean)
            ->setDefaultValue('true');
    }
}
