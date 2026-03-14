<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Enum;

enum EnvTargetEnum: string
{
    case DOT_LOCAL = '.env.local';
    case DOT_TEST = '.env.test';
    case DOT_EXAMPLE = '.env.example';

    public function getSystem(): ?KnownSystemsEnum
    {
        return null;
    }

}
