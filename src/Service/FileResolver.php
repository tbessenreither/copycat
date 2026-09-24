<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

use InvalidArgumentException;
use Tbessenreither\Copycat\Config\FileFilter;
use Tbessenreither\Copycat\Dto\FilterItem;
use Tbessenreither\Copycat\Dto\PackageInfo;
use Tbessenreither\Copycat\Enum\FilterTypeEnum;

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

    public static function resolveDirectory(PackageInfo $packageInfo, string $directory, bool $enforceScope = true): string
    {
        $resolvedDirectory = null;
        if (file_exists($directory) && is_dir($directory)) {
            $resolvedDirectory = realpath($directory);
        }

        $relativePathPackage = $packageInfo->getPackagePath() . '/' . $directory;
        if (file_exists($relativePathPackage) && is_dir($relativePathPackage)) {
            $resolvedDirectory = realpath($relativePathPackage);
        }

        $relativePathAutoload = $packageInfo->getAutoloadPath() . '/' . $directory;
        if (file_exists($relativePathAutoload) && is_dir($relativePathAutoload)) {
            $resolvedDirectory = realpath($relativePathAutoload);
        }

        if ($resolvedDirectory === null) {
            throw new InvalidArgumentException('Directory not found: ' . $directory);
        }

        if ($enforceScope === true && !str_starts_with($resolvedDirectory, $packageInfo->getPackagePath() . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Cannot access directory outside of package scope: ' . $resolvedDirectory);
        }

        return $resolvedDirectory;
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
                ConsoleOutput::debug(sprintf("Creating directory %s", $fileDir), 1);
                mkdir($projectPath . '/' . $fileDir, 0777, true);
            }
            if (!file_exists($filePath) || !is_file($filePath)) {
                ConsoleOutput::debug(sprintf("Creating file %s", $file), 1);
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

    public static function loadFile(string $file, ?FilterItem $useFilterItem = null): string
    {
        if ($useFilterItem === null) {
            $useFilterItem = FileFilter::getFilter();
        }

        if (!isset(self::$bufferedFiles[$file])) {

            ConsoleOutput::debug(sprintf("Loading file '%s'", $file), 1);
            if (!file_exists($file) || !is_file($file)) {
                throw new InvalidArgumentException('File not found: ' . $file);
            }

            $filterResult = FilterService::checkPathArray(explode(DIRECTORY_SEPARATOR, $file), $useFilterItem);
            if ($filterResult === FilterTypeEnum::BLACKLIST) {
                throw new InvalidArgumentException('File is blacklisted and cannot be loaded: ' . $file);
            }

            $fileData = file_get_contents($file);

            self::$bufferedFiles[$file] = $fileData;
        }

        return self::$bufferedFiles[$file];
    }

    public static function storeFileModification(string $file, string $content): void
    {
        ConsoleOutput::debug(sprintf("Saving file '%s'", $file), 1);
        self::$bufferedFiles[$file] = $content;
    }

    public static function writeBufferedFilesToDisk(): void
    {
        if (self::$bufferedFiles === []) {
            return;
        }

        if (ConsoleOutput::isVerbose()) {
            ConsoleOutput::heading("Writing buffered files to disk...", 0);
        }
        foreach (self::$bufferedFiles as $file => $content) {
            file_put_contents($file, $content);
            ConsoleOutput::verbose(sprintf("<dim>✓</dim> %s", FileResolver::humanizeFilePath($file)), 1);
        }
        self::$bufferedFiles = [];
    }

    /**
     * Resolve the absolute path to the project consuming this package.
     *
     * Resolution order:
     *   1. The current working directory, if it contains a `vendor/` folder.
     *      Composer sets CWD to the project root before running scripts, so
     *      this is the authoritative source when Copycat is invoked from a
     *      Composer hook.
     *   2. Splitting `__DIR__` on `vendor` and taking the segment before it.
     *      Only works when the package is installed as a regular Composer
     *      dependency; path-repository symlinks bypass `vendor/` in the file
     *      path and cannot be resolved this way.
     *
     * @throws \RuntimeException when neither strategy yields a usable path.
     */
    public static function getProjectRootDir(): string
    {
        $cwd = getcwd();
        if ($cwd !== false && is_dir($cwd . '/vendor')) {
            return $cwd;
        }

        $parts = explode('vendor', __DIR__);
        if (count($parts) > 1) {
            $resolved = realpath($parts[0]);
            if ($resolved !== false) {
                return $resolved;
            }
        }

        throw new \RuntimeException('Unable to determine project root directory.');
    }

    public static function humanizeFilePath(string $filePath): string
    {
        return ltrim(str_replace(self::getProjectRootDir(), '', $filePath), '/');
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
