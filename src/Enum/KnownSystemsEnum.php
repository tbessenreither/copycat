<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Enum;

enum KnownSystemsEnum: string
{
    case COMPOSER = 'composer';
    case DDEV = 'ddev';
    case GIT = 'git';
    case PHPSTORM = 'phpstorm';
    case SYMFONY = 'symfony';

    public function getIndicatorFile(): string
    {
        return match ($this) {
            self::COMPOSER => '/composer.json',
            self::DDEV     => '/.ddev',
            self::GIT      => '/.git',
            self::PHPSTORM => '/.idea',
            self::SYMFONY  => '/config/bundles.php',
        };
    }

    public function getIndicatorType(): string
    {
        return match ($this) {
            self::COMPOSER => 'file',
            self::DDEV     => 'directory',
            self::GIT      => 'directory',
            self::PHPSTORM => 'directory',
            self::SYMFONY  => 'file',
        };
    }

}
