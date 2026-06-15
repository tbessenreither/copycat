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
    case KIRO = '.kiro';
    case KIRO_SKILLS = '.kiro/skills';
    case KIRO_STEERING = '.kiro/steering';
    case KIRO_HOOKS = '.kiro/hooks';
    case KIRO_SPECS = '.kiro/specs';
    case KIRO_AGENTS = '.kiro/agents';
    case KIRO_SETTINGS = '.kiro/settings';
    case AGENTIC = 'agentic';
    case AGENTIC_MEMORY = 'agentic/memory';
    case AGENTIC_MEMORY_SESSIONS = 'agentic/memory/sessions';
    case AGENTIC_MEMORY_CONTEXT = 'agentic/memory/context';
    case AGENTIC_ROADMAP = 'agentic/roadmap';

    public function getSystem(): ?KnownSystemsEnum
    {
        return match ($this) {
            self::DDEV_COMMANDS_WEB, self::DDEV_COMMANDS_HOST                             => KnownSystemsEnum::DDEV,
            self::PHPSTORM_RUN_CONFIG, self::PHPSTORM_EDITOR_IDEA                         => KnownSystemsEnum::PHPSTORM,
            self::SYMFONY_BIN, self::SYMFONY_CONFIG_PACKAGES, self::SYMFONY_CONFIG_ROUTES => KnownSystemsEnum::SYMFONY,
            self::GIT_HOOKS                                                               => KnownSystemsEnum::GIT,
            self::KIRO, self::KIRO_SKILLS, self::KIRO_STEERING, self::KIRO_HOOKS, self::KIRO_SPECS, self::KIRO_AGENTS, self::KIRO_SETTINGS => KnownSystemsEnum::KIRO,
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
