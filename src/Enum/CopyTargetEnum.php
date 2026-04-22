<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Enum;

enum CopyTargetEnum: string
{
    case PROJECT_ROOT = '.';
    case DDEV_COMMANDS_WEB = '.ddev/commands/web';
    case DDEV_COMMANDS_HOST = '.ddev/commands/host';
    case PHPSTORM_EDITOR_IDEA = '.idea';
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
            self::PHPSTORM_RUN_CONFIG, self::PHPSTORM_EDITOR_IDEA                         => KnownSystemsEnum::PHPSTORM,
            self::SYMFONY_BIN, self::SYMFONY_CONFIG_PACKAGES, self::SYMFONY_CONFIG_ROUTES => KnownSystemsEnum::SYMFONY,
            self::GIT_HOOKS                                                               => KnownSystemsEnum::GIT,
            default                                                                       => null,
        };
    }

    public function isExecutable(): bool
    {
        return match ($this) {
            self::DDEV_COMMANDS_WEB,
            self::DDEV_COMMANDS_HOST,
            self::SYMFONY_BIN,
            self::GIT_HOOKS => true,

            default => false,
        };
    }

}
