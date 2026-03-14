<?php

declare(strict_types=1);

namespace Tbessenreither\MultiLevelCache;

use Tbessenreither\Copycat\Enum\CopyTargetEnum;
use Tbessenreither\Copycat\Interface\CopycatConfigInterface;
use Tbessenreither\Copycat\Interface\CopycatInterface;
use Tbessenreither\FeatureFlagServiceClient\Bundle\FeatureFlagClientBundle;

class CopycatConfig implements CopycatConfigInterface
{
    public static function run(CopycatInterface $copycat): void
    {
        $copycat->copy(
            target: CopyTargetEnum::PUBLIC,
            file: 'src/CopycatConfig.php',
            gitIgnore: true,
        );

        $copycat->symfonyBundleAdd(
            bundleClassName: FeatureFlagClientBundle::class,
        );
    }

}
