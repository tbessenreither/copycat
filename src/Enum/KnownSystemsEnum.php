<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Enum;

enum KnownSystemsEnum: string
{
    case SYMFONY = 'symfony';
    case DDEV = 'ddev';
    case GIT = 'git';
    case COMPOSER = 'composer';

    public function getIndicatorFile(): string
    {
        return match ($this) {
            self::SYMFONY  => '/config/bundles.php',
            self::DDEV     => '/.ddev',
            self::GIT      => '/.git',
            self::COMPOSER => '/composer.json',
        };
    }

    public function getIndicatorType(): string
    {
        return match ($this) {
            self::SYMFONY  => 'file',
            self::DDEV     => 'directory',
            self::GIT      => 'directory',
            self::COMPOSER => 'file',
        };
    }

}
