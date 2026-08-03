<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Enum;

use Tbessenreither\Copycat\Dto\SystemIndicator;

enum KnownSystemsEnum: string
{
    case COMPOSER = 'composer';
    case DDEV = 'ddev';
    case GIT = 'git';
    case PHPSTORM = 'phpstorm';
    case SYMFONY = 'symfony';
    case VSCODE = 'vscode';
    case KIRO = 'kiro';
    case CLAUDE = 'claude';


    /**
     * Returns an array of SystemIndicator Objects which are to be evaluated via OR concatination
     * @return SystemIndicator[]
     */
    public function getIndicators(): array
    {
        return match ($this) {
            self::COMPOSER => [new SystemIndicator(SystemIndicatorTypeEnum::FILE, '/composer.json')],
            self::DDEV     => [new SystemIndicator(SystemIndicatorTypeEnum::DIRECTORY, '/.ddev')],
            self::GIT      => [new SystemIndicator(SystemIndicatorTypeEnum::DIRECTORY, '/.git')],
            self::PHPSTORM => [new SystemIndicator(SystemIndicatorTypeEnum::DIRECTORY, '/.idea'), new SystemIndicator(SystemIndicatorTypeEnum::ENV, 'IDE=phpstorm')],
            self::SYMFONY  => [new SystemIndicator(SystemIndicatorTypeEnum::FILE, '/config/bundles.php')],
            self::VSCODE   => [new SystemIndicator(SystemIndicatorTypeEnum::DIRECTORY, '/.vscode'), new SystemIndicator(SystemIndicatorTypeEnum::ENV, 'IDE=vscode')],
            self::KIRO     => [new SystemIndicator(SystemIndicatorTypeEnum::DIRECTORY, '/.kiro')],
            self::CLAUDE   => [new SystemIndicator(SystemIndicatorTypeEnum::DIRECTORY, '/.claude')],
        };
    }

}
