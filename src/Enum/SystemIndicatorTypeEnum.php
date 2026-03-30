<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Enum;

enum SystemIndicatorTypeEnum: string
{
    case DIRECTORY = 'directory';
    case FILE = 'file';
    case ENV = 'env';
}
