<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Interface;

use Tbessenreither\Copycat\Dto\FilterItem;

interface FilterProvider
{
    public static function getFilter(): FilterItem;
}
