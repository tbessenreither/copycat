<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Dto;

use InvalidArgumentException;
use Tbessenreither\Copycat\Enum\FilterTypeEnum;

class FilterItem
{
    /**
     * @param FilterItem[] $children
     * @throws InvalidArgumentException
     */
    public function __construct(
        private string $key,
        private FilterTypeEnum $type,
        private array $children = [],
    ) {
        foreach ($this->children as $child) {
            if (
                !$child instanceof FilterItem
            ) {
                throw new InvalidArgumentException('Children must be of type FilterItem');
            }
        }
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getType(): FilterTypeEnum
    {
        return $this->type;
    }

    public function getChildren(): array
    {
        return $this->children;
    }
}
