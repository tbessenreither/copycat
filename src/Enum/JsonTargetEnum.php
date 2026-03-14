<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Enum;

enum JsonTargetEnum: string
{
    case COMPOSER_JSON = 'composer.json';

    public function getSystem(): ?KnownSystemsEnum
    {
        return match ($this) {
            self::COMPOSER_JSON => KnownSystemsEnum::COMPOSER,
            default             => null
        };
    }

    public function allowedPaths(): ?array
    {
        return match ($this) {
            self::COMPOSER_JSON => [
                'extra',
            ],
            default             => null,
        };
    }

    public function canRemoveValues(): bool
    {
        return match ($this) {
            self::COMPOSER_JSON => true,
            default             => false,
        };
    }

}
