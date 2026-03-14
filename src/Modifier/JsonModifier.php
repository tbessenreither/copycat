<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Modifier;

use InvalidArgumentException;
use RuntimeException;
use Tbessenreither\Copycat\Enum\JsonTargetEnum;

class JsonModifier
{
    public static function add(string $fileContent, string $path, mixed $value, bool $overwrite = false): string
    {
        $jsonData = json_decode($fileContent, true);

        $keys = explode('.', $path);
        $current = &$jsonData;
        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = &$current[$key];

                continue;
            } elseif (is_array($current) && !array_key_exists($key, $current)) {
                $current[$key] = null;
                $current = &$current[$key];

                continue;
            } elseif (!is_array($current)) {
                if ($current === null) {
                    $current = [];
                    $current[$key] = null;
                    $current = &$current[$key];

                    continue;
                } elseif ($overwrite) {
                    $current = [];
                    $current[$key] = null;
                    $current = &$current[$key];

                    continue;
                } else {
                    throw new RuntimeException('Cannot add value at path "' . $path . '" because there is a non-array value at "' . implode('.', array_slice($keys, 0, count($keys) - 1)) . '" and overwrite is set to false.');
                }
            } else {
                throw new RuntimeException('Unhandled data structure at path "' . implode('.', array_slice($keys, 0, count($keys) - 1)) . '".');
            }
        }

        if (
            $current !== null && $current !== []
            && !$overwrite
        ) {
            throw new RuntimeException('Cannot add value at path "' . $path . '" because there is already a value at that path and overwrite is set to false.');
        }

        $current = $value;

        $fileContentModified = json_encode($jsonData, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);

        return $fileContentModified;
    }

    public static function remove(string $fileContent, string $path): string
    {
        $jsonData = json_decode($fileContent, true);

        $keys = explode('.', $path);
        $lastKey = array_pop($keys);

        $current = &$jsonData;
        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = &$current[$key];

                continue;
            } else {
                throw new RuntimeException('No entry found at path "' . $path . '", skipping.');
            }
        }

        // we are at the target path, remove it
        if (!is_array($current) || !array_key_exists($lastKey, $current)) {
            throw new RuntimeException('No entry found at path "' . $path . '", skipping.');
        }

        echo "        Removing entry at path " . $path . "." . PHP_EOL;
        unset($current[$lastKey]);

        $fileContentModified = json_encode($jsonData, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);

        return $fileContentModified;
    }

    public static function securityChecks(JsonTargetEnum $target, string $path): void
    {
        $allowedPaths = $target->allowedPaths();
        if ($allowedPaths === null) {
            return;
        }

        foreach ($allowedPaths as $allowedPath) {
            if (str_starts_with($path . '.', $allowedPath . '.')) {
                return;
            }
        }

        throw new InvalidArgumentException('The target "' . $target->value . '" only allows modifications at the following paths: ' . implode(', ', $allowedPaths) . '. Your modification target was not in the allowed paths list.');
    }

}
