<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

use InvalidArgumentException;
use Tbessenreither\Copycat\Dto\PackageInfo;

class FileResolver
{
    private static array $bufferedFiles = [];

    public static function resolve(PackageInfo $packageInfo, string $file, bool $enforceScope = true): string
    {
        $resolvedFile = null;
        if (file_exists($file) && is_file($file)) {
            $resolvedFile = realpath($file);
        }

        $relativePathPackage = $packageInfo->getPackagePath() . '/' . $file;
        if (file_exists($relativePathPackage) && is_file($relativePathPackage)) {
            $resolvedFile = realpath($relativePathPackage);
        }

        $relativePathAutoload = $packageInfo->getAutoloadPath() . '/' . $file;
        if (file_exists($relativePathAutoload) && is_file($relativePathAutoload)) {
            $resolvedFile = realpath($relativePathAutoload);
        }

        if ($resolvedFile === null) {
            throw new InvalidArgumentException('File not found: ' . $file);
        }

        if ($enforceScope === true && !str_starts_with($resolvedFile, $packageInfo->getPackagePath() . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Cannot access file outside of package scope: ' . $resolvedFile);
        }

        return $resolvedFile;
    }

    public static function resolveInProject(PackageInfo $packageInfo, string $file, bool $createIfNotExists = false): string
    {
        $projectPath = $packageInfo->getProjectPath();
        $filePath = $projectPath . '/' . $file;

        if ($createIfNotExists) {
            $pathParts = explode('/', $file);
            array_pop($pathParts);

            $fileDir = implode('/', $pathParts);
            if (!is_dir($projectPath . '/' . $fileDir)) {
                echo "      Directory " . $fileDir . " does not exist, creating it." . PHP_EOL;
                mkdir($projectPath . '/' . $fileDir, 0777, true);
            }
            if (!file_exists($filePath) || !is_file($filePath)) {
                echo "      File " . $file . " does not exist, creating it." . PHP_EOL;
                touch($filePath);
            }

        }
        $resolvedFile = realpath($filePath);

        if ($resolvedFile === false) {
            throw new InvalidArgumentException('File not found: ' . $filePath);
        }


        if (!str_starts_with($resolvedFile, $projectPath)) {
            throw new InvalidArgumentException('Resolved file is outside of project scope: ' . $file);
        }

        if (str_starts_with($resolvedFile, $projectPath . 'vendor')) {
            throw new InvalidArgumentException('Resolved file is inside vendor directory, which is not allowed: ' . $file);
        }

        if ($resolvedFile === false || !file_exists($resolvedFile) || !is_file($resolvedFile)) {
            throw new InvalidArgumentException('Project file not found: ' . $file);
        }

        return $resolvedFile;
    }

    public static function loadFile(string $file): string
    {
        if (!isset(self::$bufferedFiles[$file])) {

            echo "        Loading file: $file" . PHP_EOL;
            if (!file_exists($file) || !is_file($file)) {
                throw new InvalidArgumentException('File not found: ' . $file);
            }
            $fileData = file_get_contents($file);

            self::$bufferedFiles[$file] = $fileData;
        }

        return self::$bufferedFiles[$file];
    }

    public static function storeFileModification(string $file, string $content): void
    {
        echo "        Storing modifications for: $file" . PHP_EOL;
        self::$bufferedFiles[$file] = $content;
    }

    public static function writeBufferedFilesToDisk(): void
    {
        echo PHP_EOL . "Writing buffered file modifications to disk..." . PHP_EOL;
        foreach (self::$bufferedFiles as $file => $content) {
            echo "    - Writing file to disk: $file" . PHP_EOL;
            file_put_contents($file, $content);
        }
        self::$bufferedFiles = [];
    }

    public static function getProjectRootDir(): string
    {
        return realpath(explode('vendor', __DIR__)[0]);
    }

    public static function resolveFileByPriority(array $possibleFiles): string
    {
        foreach ($possibleFiles as $file) {
            if (file_exists($file) && is_file($file)) {
                return rtrim(realpath($file), DIRECTORY_SEPARATOR);
            }
        }

        throw new InvalidArgumentException('None of the possible files could be resolved: ' . implode(', ', $possibleFiles));
    }

}
