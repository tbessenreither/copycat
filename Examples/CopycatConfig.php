<?php

declare(strict_types=1);

namespace Tbessenreither\MultiLevelCache;

use Tbessenreither\Copycat\Enum\CopyTargetEnum;
use Tbessenreither\Copycat\Enum\JsonTargetEnum;
use Tbessenreither\Copycat\Interface\CopycatConfigInterface;
use Tbessenreither\Copycat\Interface\CopycatInterface;

class CopycatConfig implements CopycatConfigInterface
{
    public static function run(CopycatInterface $copycat): void
    {
        $copycat->copy(
            target: CopyTargetEnum::DDEV_COMMANDS_WEB,
            file: 'ddev/commands/web/test-command.sh',
            gitIgnore: true,
        );

        $copycat->jsonAdd(
            target: JsonTargetEnum::TEST,
            path: 'items',
            value: ['item4', 'item5']
        );
        $copycat->jsonAdd(
            target: JsonTargetEnum::TEST,
            path: 'config',
            value: [
                'setting1' => 'value1 ' . time(),
                'setting2' => 'value2 ' . time(),
            ],
        );
        $copycat->jsonAdd(
            target: JsonTargetEnum::TEST,
            path: 'config.setting3',
            value: 'value3 ' . time(),
        );
        $copycat->jsonAdd(
            target: JsonTargetEnum::TEST,
            path: 'nested.level1.level2.level3',
            value: 'nested value ' . time(),
        );

        $copycat->gitIgnoreAdd(
            entries: [
                CopyTargetEnum::DDEV_COMMANDS_WEB->value . '/mlc-make',
                CopyTargetEnum::DDEV_COMMANDS_WEB->value . '/mlc-update',
            ]
        );

        /* * Symfony specific configuration
        $copycat->symfonyBundleAdd(
            bundleClassName: MultiLevelCacheBundle::class,
        );
        /**/

        $copycat->symfonyAddServiceToYaml(
            serviceClass: CopycatConfig::class,
            arguments: [
                '$packageInfo' => 'Tbessenreither\MultiLevelCache\Dto\PackageInfo',
            ],
        );
    }

}
