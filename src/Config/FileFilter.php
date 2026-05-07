<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Config;

use Tbessenreither\Copycat\Dto\FilterItem;
use Tbessenreither\Copycat\Enum\FilterTypeEnum;
use Tbessenreither\Copycat\Interface\FilterProvider;

class FileFilter implements FilterProvider
{
    public static function getFilter(): FilterItem
    {
        return new FilterItem(
            key: 'root',
            type: FilterTypeEnum::NONE,
            children: [
                new FilterItem(
                    key: 'composer.lock',
                    type: FilterTypeEnum::BLACKLIST,
                ),
            ]
        );
    }
}
