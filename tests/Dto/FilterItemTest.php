<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Dto;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Tbessenreither\Copycat\Dto\FilterItem;
use Tbessenreither\Copycat\Enum\FilterTypeEnum;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(FilterItem::class)]
class FilterItemTest extends TestCase
{
    public function testSetterAndGetter(): void
    {
        $filterItem = new FilterItem(
            key: 'testKey',
            type: FilterTypeEnum::NONE,
            children: [
                new FilterItem(
                    key: 'childKey1',
                    type: FilterTypeEnum::BLACKLIST,
                ),
            ],
        );

        $this->assertEquals('testKey', $filterItem->getKey());
        $this->assertEquals(FilterTypeEnum::NONE, $filterItem->getType());
        $this->assertCount(1, $filterItem->getChildren());
        $this->assertEquals('childKey1', $filterItem->getChildren()[0]->getKey());
        $this->assertEquals(FilterTypeEnum::BLACKLIST, $filterItem->getChildren()[0]->getType());
        $this->assertEmpty($filterItem->getChildren()[0]->getChildren());
    }

    public function testConstructorValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterItem(
            key: 'testKey',
            type: FilterTypeEnum::NONE,
            children: [
                new stdClass(), // Invalid child
            ],
        );
    }
}
