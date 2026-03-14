<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Modifier;

use InvalidArgumentException;
use RuntimeException;

class FileCopy
{
    public static function copy(string $source, string $destinationDirectory, bool $overwrite = true, bool $createTargetDirectory = false): void
    {
        if (!file_exists($source) || !is_file($source)) {
            throw new InvalidArgumentException('Source file does not exist: ' . $source);
        }

        if ($createTargetDirectory) {
            self::ensureDirectoryExists($destinationDirectory);
        }

        if (!file_exists($destinationDirectory) || !is_dir($destinationDirectory)) {
            throw new InvalidArgumentException('Destination directory does not exist: ' . $destinationDirectory);
        }

        $destination = rtrim($destinationDirectory, '/') . '/' . basename($source);

        if (!$overwrite && file_exists($destination)) {
            throw new RuntimeException('Destination file already exists: ' . $destination);
        }

        if (!copy($source, $destination)) {
            throw new RuntimeException('Failed to copy file from ' . $source . ' to ' . $destination);
        }
    }

    public static function remove(string $source, string $destinationDirectory): void
    {
        if (!file_exists($destinationDirectory) || !is_dir($destinationDirectory)) {
            throw new InvalidArgumentException('Destination directory does not exist: ' . $destinationDirectory);
        }

        $destination = rtrim($destinationDirectory, '/') . '/' . basename($source);

        if (!file_exists($destination) || !is_file($destination)) {
            throw new InvalidArgumentException('Destination file does not exist: ' . $destination);
        }

        if (!unlink($destination)) {
            throw new RuntimeException('Failed to remove file at ' . $destination);
        }
    }

    private static function ensureDirectoryExists(string $directory): void
    {
        if (!file_exists($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException('Failed to create directory: ' . $directory);
            }
        } elseif (!is_dir($directory)) {
            throw new InvalidArgumentException('Path exists but is not a directory: ' . $directory);
        }
    }

}
