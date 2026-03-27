<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tbessenreither\Copycat\Dto\SystemIndicator;
use Tbessenreither\Copycat\Enum\KnownSystemsEnum;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(KnownSystemsEnum::class)]
#[UsesClass(SystemIndicator::class)]
class KnownSystemsEnumTest extends TestCase
{
    public function testIndicatorMatching(): void
    {
        foreach (KnownSystemsEnum::cases() as $case) {
            $indicators = $case->getIndicators();
            $this->assertIsArray($indicators, 'Expected indicators for "' . $case->name . '" to be an array.');
            foreach ($indicators as $indicator) {
                $this->assertInstanceOf(SystemIndicator::class, $indicator, 'Expected indicator for "' . $case->name . '" to be an instance of SystemIndicator.');
            }
        }
    }
}
