<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use Tbessenreither\Copycat\Dto\SystemIndicator;
use Tbessenreither\Copycat\Enum\SystemIndicatorTypeEnum;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(SystemIndicator::class)]
class SystemIndicatorTest extends TestCase
{
    public function testSetterAndGetter(): void
    {
        $systemIndicator = new SystemIndicator(
            type: SystemIndicatorTypeEnum::FILE,
            value: '/path/to/file'
        );

        $this->assertEquals(SystemIndicatorTypeEnum::FILE, $systemIndicator->getType());
        $this->assertEquals('/path/to/file', $systemIndicator->getValue());
    }
}
