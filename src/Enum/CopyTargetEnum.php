<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Enum;

enum CopyTargetEnum: string
{
    case PROJECT_ROOT = '.';
    case DDEV_COMMANDS_WEB = '.ddev/commands/web';
    case DDEV_COMMANDS_HOST = '.ddev/commands/host';
    case PHPSTORM_RUN_CONFIG = '.run';
    case SYMFONY_BIN = 'bin';
    case SYMFONY_CONFIG_PACKAGES = 'config/packages';
    case SYMFONY_CONFIG_ROUTES = 'config/routes';
    case PUBLIC = 'public';
    case COPYCAT_CONFIG = '.copycat';
    case GIT_HOOKS = '.git/hooks';

    public function getSystem(): ?KnownSystemsEnum
    {
        return match ($this) {
            self::DDEV_COMMANDS_WEB, self::DDEV_COMMANDS_HOST                             => KnownSystemsEnum::DDEV,
            self::PHPSTORM_RUN_CONFIG                                                     => KnownSystemsEnum::SYMFONY,
            self::SYMFONY_BIN, self::SYMFONY_CONFIG_PACKAGES, self::SYMFONY_CONFIG_ROUTES => KnownSystemsEnum::SYMFONY,
            self::GIT_HOOKS                                                               => KnownSystemsEnum::GIT,
            default                                                                       => null,
        };
    }

}
