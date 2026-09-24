<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tbessenreither\Copycat\Enum\VerbosityEnum;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(VerbosityEnum::class)]
class VerbosityEnumTest extends TestCase
{
    public function testFromStringOrNullParsesLevelNamesCaseInsensitively(): void
    {
        $this->assertSame(VerbosityEnum::SILENT, VerbosityEnum::fromStringOrNull('silent'));
        $this->assertSame(VerbosityEnum::NORMAL, VerbosityEnum::fromStringOrNull('Normal'));
        $this->assertSame(VerbosityEnum::VERBOSE, VerbosityEnum::fromStringOrNull('VERBOSE'));
        $this->assertSame(VerbosityEnum::DEBUG, VerbosityEnum::fromStringOrNull(' debug '));
    }

    public function testFromStringOrNullParsesNumericLevels(): void
    {
        $this->assertSame(VerbosityEnum::SILENT, VerbosityEnum::fromStringOrNull('0'));
        $this->assertSame(VerbosityEnum::NORMAL, VerbosityEnum::fromStringOrNull('1'));
        $this->assertSame(VerbosityEnum::VERBOSE, VerbosityEnum::fromStringOrNull('2'));
        $this->assertSame(VerbosityEnum::DEBUG, VerbosityEnum::fromStringOrNull('3'));
    }

    public function testFromStringOrNullReturnsNullForInvalidInput(): void
    {
        $this->assertNull(VerbosityEnum::fromStringOrNull(null));
        $this->assertNull(VerbosityEnum::fromStringOrNull(''));
        $this->assertNull(VerbosityEnum::fromStringOrNull('   '));
        $this->assertNull(VerbosityEnum::fromStringOrNull('loud'));
        $this->assertNull(VerbosityEnum::fromStringOrNull('99'));
    }
}
