<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

use RuntimeException;
use Tbessenreither\Copycat\Dto\PackageInfo;
use Tbessenreither\Copycat\Dto\SystemIndicator;
use Tbessenreither\Copycat\Enum\KnownSystemsEnum;
use Tbessenreither\Copycat\Enum\SystemIndicatorTypeEnum;
use Throwable;

class SystemValidator
{
    public static function validateSystem(PackageInfo $packageInfo, ?KnownSystemsEnum $system): void
    {
        if ($system === null) {
            return;
        }

        if (!self::checkForSystem(packageInfo: $packageInfo, system: $system)) {
            throw new RuntimeException('The current project does not appear to be a ' . $system->value . ' project. Aborting operation.');
        }
    }

    private static function checkForSystem(PackageInfo $packageInfo, ?KnownSystemsEnum $system): bool
    {
        if ($system === null) {
            return false;
        }

        $indicators = $system->getIndicators();

        foreach ($indicators as $indicator) {
            try {
                if (self::checkIndicator($packageInfo, $indicator)) {
                    return true;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return false;
    }

    private static function checkIndicator(PackageInfo $packageInfo, SystemIndicator $indicator): bool
    {
        switch ($indicator->getType()) {
            case SystemIndicatorTypeEnum::FILE:
                return file_exists($packageInfo->getProjectPath() . $indicator->getValue()) && is_file($packageInfo->getProjectPath() . $indicator->getValue());
            case SystemIndicatorTypeEnum::ENV:
                return self::checkForEnvVariable($indicator->getValue());
            case SystemIndicatorTypeEnum::DIRECTORY:
            default:
                return file_exists($packageInfo->getProjectPath() . $indicator->getValue()) && is_dir($packageInfo->getProjectPath() . $indicator->getValue());
        }
    }

    private static function checkForEnvVariable(string $indicatorValue): bool
    {
        if (strpos($indicatorValue, '!=') !== false) {
            [$envVariable, $unexpectedValue] = explode('!=', $indicatorValue, 2);
            $envValue = self::getEnvVariableValue($envVariable);

            return $envValue !== $unexpectedValue;
        } elseif (strpos($indicatorValue, '=') !== false) {
            [$envVariable, $expectedValue] = explode('=', $indicatorValue, 2);
            $envValue = self::getEnvVariableValue($envVariable);

            return $envValue === $expectedValue;
        } elseif (str_contains($indicatorValue, ':')) {
            [$expectation, $envVariable] = explode(':', $indicatorValue, 2);
            $envValue = self::getEnvVariableValue($envVariable);

            if ($expectation === 'exists') {
                return $envValue !== false;
            } elseif ($expectation === '!exists') {
                return $envValue === false;
            }
        }

        throw new RuntimeException('Invalid environment variable indicator format: ' . $indicatorValue);
    }

    private static function getEnvVariableValue(string $envVariable): bool|null|string
    {
        $value = $_ENV[$envVariable] ?? getenv($envVariable);

        return $value;
    }

}
