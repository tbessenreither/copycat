<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Dto;

class EnvVar
{
    public function __construct(
        private string $name,
        private string|bool|int|float|null $value = null,
        private string $description = '',
        private bool $isFlag = false,
    ) {
    }

    public function __toString(): string
    {
        $valueString = '';

        if ($this->isFlag()) {
            $valueString = $this->getName() . '=""';
        } elseif (preg_match('/^[A-Za-z0-9_\.\/-]+$/', $this->getValueAsString())) {
            $valueString = $this->getName() . '=' . $this->getValueAsString();
        } else {
            $escapedValue = addcslashes($this->getValueAsString(), "\\\"\n\r\t$");
            $valueString = $this->getName() . "=\"{$escapedValue}\"";
        }

        $descriptors = [];
        if ($this->isFlag()) {
            $descriptors[] = 'Flag';
        }

        if (!empty($this->description)) {
            $descriptors[] = $this->getDescription();
        }

        if (!empty($descriptors)) {
            $valueString .= ' # ' . implode(' # ', $descriptors);
        }

        return $valueString;
    }

    public function getName(): string
    {
        return mb_strtoupper($this->name);
    }

    public function getValue(): string|bool|int|float|null
    {
        return $this->value;
    }

    public function getValueAsString(): string
    {
        if (is_bool($this->getValue())) {
            return $this->getValue() ? 'true' : 'false';
        } elseif (is_null($this->getValue())) {
            return 'null';
        } else {
            return (string) $this->getValue();
        }
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isFlag(): bool
    {
        return $this->isFlag;
    }
}
