<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Enum;

enum FilterTypeEnum
{
    case BLACKLIST;
    case WHITELIST;
    case NONE;
}
