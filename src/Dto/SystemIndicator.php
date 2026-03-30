<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Dto;

use Tbessenreither\Copycat\Enum\SystemIndicatorTypeEnum;

class SystemIndicator
{
    public function __construct(
        private SystemIndicatorTypeEnum $type,
        private string $value,
    ) {
    }

    public function getType(): SystemIndicatorTypeEnum
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
