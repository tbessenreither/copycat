<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Enum;

enum EnvTargetEnum: string
{
    case DOT_ENV = '.env';
    case DOT_DEV = '.env.dev';
    case DOT_EXAMPLE = '.env.example';
    case DOT_LOCAL = '.env.local';
    case DOT_TEST = '.env.test';

    public function getSystem(): ?KnownSystemsEnum
    {
        return null;
    }

}
