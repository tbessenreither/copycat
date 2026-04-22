<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tbessenreither\Copycat\Enum\CopyTargetEnum;
use Tbessenreither\Copycat\Enum\KnownSystemsEnum;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(CopyTargetEnum::class)]
#[UsesClass(KnownSystemsEnum::class)]
class CopyTargetEnumTest extends TestCase
{
    public function testIndicatorMatching(): void
    {
        foreach (CopyTargetEnum::cases() as $case) {
            $system = $case->getSystem();
            $this->assertTrue(
                $system === null || $system instanceof KnownSystemsEnum,
                sprintf('Expected getSystem() to return null or an instance of KnownSystemsEnum for case %s', $case->name)
            );
            $this->assertIsBool(
                $case->isExecutable(),
                sprintf('Expected isExecutable() to return a boolean for case %s', $case->name)
            );
        }
    }
}
