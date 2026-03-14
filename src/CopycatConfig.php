<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat;

use Tbessenreither\Copycat\Enum\JsonTargetEnum;
use Tbessenreither\Copycat\Interface\CopycatConfigInterface;
use Tbessenreither\Copycat\Interface\CopycatInterface;
use Tbessenreither\Copycat\Service\ConfigLoader;

class CopycatConfig implements CopycatConfigInterface
{
    public static function run(CopycatInterface $copycat): void
    {
        $config = ConfigLoader::getCopycatConfig();
        $copycat->jsonAdd(
            target: JsonTargetEnum::COMPOSER_JSON,
            path: 'extra.copycat',
            value: $config,
            overwrite: false,
        );
    }

}
