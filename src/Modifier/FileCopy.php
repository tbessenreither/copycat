<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Modifier;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class FileCopy
{
    public static function copy(string $source, string $destinationDirectory, bool $overwrite = true, bool $createTargetDirectory = false, bool $executable = false): void
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

        if ($executable) {
            if (!chmod($destination, 0755)) {
                throw new RuntimeException('Failed to set executable permissions for ' . $destination);
            }
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

    public static function copyDirectory(string $sourceDirectory, string $destinationDirectory, bool $overwrite = true, bool $createTargetDirectory = false): void
    {
        if (!file_exists($sourceDirectory) || !is_dir($sourceDirectory)) {
            throw new InvalidArgumentException('Source directory does not exist: ' . $sourceDirectory);
        }

        if ($createTargetDirectory) {
            self::ensureDirectoryExists($destinationDirectory);
        }

        if (!file_exists($destinationDirectory) || !is_dir($destinationDirectory)) {
            throw new InvalidArgumentException('Destination directory does not exist: ' . $destinationDirectory);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $destPath = $destinationDirectory . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                self::ensureDirectoryExists($destPath);
            } else {
                self::copy($item->getPathname(), dirname($destPath), $overwrite, $createTargetDirectory, false);
            }
        }
    }

    public static function removeDirectory(string $sourceDirectory, string $destinationDirectory): void
    {
        if (!file_exists($sourceDirectory) || !is_dir($sourceDirectory)) {
            throw new InvalidArgumentException('Source directory does not exist: ' . $sourceDirectory);
        }
        if (!file_exists($destinationDirectory)) {
            // Destination directory does not exist, nothing to remove
            return;
        }

        // use iterator to get all files and directories that need to be removed
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $checkEmptyDirectories = [];
        foreach ($iterator as $item) {
            $destPath = $destinationDirectory . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isFile()) {
                if (file_exists($destPath) && is_file($destPath)) {
                    unlink($destPath);
                }
            } elseif ($item->isDir()) {
                $checkEmptyDirectories[] = $destPath;
            }
        }

        foreach (array_reverse($checkEmptyDirectories) as $dir) {
            if (is_dir($dir) && count(scandir($dir)) === 2) {
                rmdir($dir);
            }
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
