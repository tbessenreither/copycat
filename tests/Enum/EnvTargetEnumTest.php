<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tbessenreither\Copycat\Enum\EnvTargetEnum;
use Tbessenreither\Copycat\Enum\KnownSystemsEnum;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(EnvTargetEnum::class)]
#[UsesClass(KnownSystemsEnum::class)]
class EnvTargetEnumTest extends TestCase
{
    public function testIndicatorMatching(): void
    {
        foreach (EnvTargetEnum::cases() as $case) {
            $system = $case->getSystem();
            $this->assertTrue(
                $system === null || $system instanceof KnownSystemsEnum,
                sprintf('Expected getSystem() to return null or an instance of KnownSystemsEnum for case %s', $case->name)
            );
        }
    }
}
