<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tbessenreither\Copycat\Enum\JsonTargetEnum;
use Tbessenreither\Copycat\Enum\KnownSystemsEnum;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(JsonTargetEnum::class)]
#[UsesClass(KnownSystemsEnum::class)]
class JsonTargetEnumTest extends TestCase
{
    public function testIndicatorMatching(): void
    {
        foreach (JsonTargetEnum::cases() as $case) {
            $system = $case->getSystem();
            $this->assertTrue(
                $system === null || $system instanceof KnownSystemsEnum,
                sprintf('Expected getSystem() to return null or an instance of KnownSystemsEnum for case %s', $case->name)
            );
            $allowedPaths = $case->allowedPaths();
            $this->assertTrue(
                $allowedPaths === null || is_array($allowedPaths),
                sprintf('Expected allowedPaths() to return null or an array for case %s', $case->name)
            );
            $canRemoveValues = $case->canRemoveValues();
            $this->assertIsBool($canRemoveValues, sprintf('Expected canRemoveValues() to return a boolean for case %s', $case->name));
        }
    }
}
