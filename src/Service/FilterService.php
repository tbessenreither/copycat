<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

use Tbessenreither\Copycat\Dto\FilterItem;
use Tbessenreither\Copycat\Enum\FilterTypeEnum;

class FilterService
{
    public static function checkPathArray(array $pathArray, FilterItem $filterItem): FilterTypeEnum
    {
        $matchedFilterItem = self::matchKeyToFilterItemChild($pathArray[0], $filterItem);

        if ($matchedFilterItem === null) {
            return FilterTypeEnum::NONE;
        } elseif ($matchedFilterItem->getType() === FilterTypeEnum::BLACKLIST) {
            return FilterTypeEnum::BLACKLIST;
        } elseif ($matchedFilterItem->getType() === FilterTypeEnum::WHITELIST) {
            return FilterTypeEnum::WHITELIST;
        } else {
            array_shift($pathArray);

            return self::checkPathArray($pathArray, $matchedFilterItem);
        }
    }

    public static function matchKeyToFilterItemChild(string $key, FilterItem $filterItem): ?FilterItem
    {
        foreach ($filterItem->getChildren() as $childFilterItem) {
            if ($childFilterItem->getKey() === $key) {
                return $childFilterItem;
            }
        }

        return null;
    }
}
