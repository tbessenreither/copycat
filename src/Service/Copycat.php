<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

use InvalidArgumentException;
use Tbessenreither\Copycat\Dto\EnvVar;
use Tbessenreither\Copycat\Enum\CopyTargetEnum;
use Tbessenreither\Copycat\Enum\EnvTargetEnum;
use Tbessenreither\Copycat\Enum\JsonTargetEnum;
use Tbessenreither\Copycat\Enum\KnownSystemsEnum;
use Tbessenreither\Copycat\Interface\CopycatInterface;
use Tbessenreither\Copycat\Modifier\EnvModifier;
use Tbessenreither\Copycat\Modifier\FileCopy;
use Tbessenreither\Copycat\Modifier\GitignoreModifier;
use Tbessenreither\Copycat\Modifier\JsonModifier;
use Tbessenreither\Copycat\Modifier\SymfonyModifier;
use Throwable;

class Copycat extends CopycatBase implements CopycatInterface
{
    /**
     * Copies a file from the package to the specified target location in the project.
     * This method does not create directories if they do not exist, so the target directory must already exist before calling this method.
     */
    public function copy(CopyTargetEnum $target, string $file, bool $overwrite = true, bool $gitIgnore = false, bool $createTargetDirectory = false): void
    {
        try {
            echo '    - copy ' . $file . ' to ' . $target->value . '' . PHP_EOL;
            SystemValidator::validateSystem($this->packageInfo, $target->getSystem());

            $file = FileResolver::resolve(
                packageInfo: $this->packageInfo,
                file: $file,
            );

            FileCopy::copy(
                source: $file,
                destinationDirectory: $this->getTargetDir($target),
                overwrite: $overwrite,
                createTargetDirectory: $createTargetDirectory,
            );

            if ($gitIgnore) {
                $gitignoreValue = $target->value . '/' . basename($file);
                if (str_starts_with($gitignoreValue, './')) {
                    $gitignoreValue = substr($gitignoreValue, 2);
                }
                $this->gitIgnoreAdd($gitignoreValue);
            }

        } catch (Throwable $e) {
            $this->logError('copy', $e);
        }
    }

    public function copyDirectory(CopyTargetEnum $target, string $source, bool $overwrite = true, bool $gitIgnore = false, bool $createTargetDirectory = false): void
    {
        try {
            echo '    - copy directory ' . $source . ' to ' . $target->value . '' . PHP_EOL;
            SystemValidator::validateSystem($this->packageInfo, $target->getSystem());

            $sourceDir = FileResolver::resolveDirectory(
                packageInfo: $this->packageInfo,
                directory: $source,
            );

            if (!is_dir($sourceDir)) {
                throw new InvalidArgumentException('Source directory does not exist: ' . $sourceDir);
            }

            $destinationDir = $this->getTargetDir($target);

            FileCopy::copyDirectory(
                sourceDirectory: $sourceDir,
                destinationDirectory: $destinationDir,
                overwrite: $overwrite,
                createTargetDirectory: $createTargetDirectory,
            );

        } catch (Throwable $e) {
            $this->logError('copyDirectory', $e);
        }
    }

    public function jsonAdd(JsonTargetEnum $target, string $path, mixed $value, bool $overwrite = false): void
    {
        try {
            echo "    - Adding value to " . $target->value . " at path " . $path . PHP_EOL;

            JsonModifier::securityChecks(target: $target, path: $path);
            SystemValidator::validateSystem($this->packageInfo, $target->getSystem());

            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: $target->value,
            );

            $jsonModified = JsonModifier::add(
                fileContent: FileResolver::loadFile($file),
                path: $path,
                value: $value,
                overwrite: $overwrite,
            );

            FileResolver::storeFileModification($file, $jsonModified);

        } catch (Throwable $e) {
            $this->logError('jsonAdd', $e);
        }
    }

    /**
     * Adds one or more entries to the .gitignore file in project root. If the .gitignore file does not exist, it will be created.
     * @param string|string[] $entry
     * @return void
     */
    public function gitIgnoreAdd(string|array $entries): void
    {
        if (!is_array($entries)) {
            $entries = [$entries];
        }
        try {
            echo "    - Adding " . count($entries) . " entries to .gitignore:" . PHP_EOL;
            SystemValidator::validateSystem($this->packageInfo, KnownSystemsEnum::GIT);
            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: '.gitignore',
                createIfNotExists: true,
            );

            $modifiedContent = GitignoreModifier::add(
                fileContent: FileResolver::loadFile($file),
                entries: $entries,
                groupName: $this->packageInfo->getNamespace(),
            );

            FileResolver::storeFileModification($file, $modifiedContent);

        } catch (Throwable $e) {
            $this->logError('gitIgnoreAdd', $e);
        }
    }

    public function symfonyBundleAdd(string $bundleClassName): void
    {
        try {
            echo "    - Adding $bundleClassName to symfony bundles.php." . PHP_EOL;
            SystemValidator::validateSystem($this->packageInfo, KnownSystemsEnum::SYMFONY);

            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: 'config/bundles.php',
            );

            $modifiedContent = SymfonyModifier::addToBundle(
                fileContent: FileResolver::loadFile($file),
                bundleClassName: $bundleClassName,
            );

            FileResolver::storeFileModification($file, $modifiedContent);

        } catch (Throwable $e) {
            $this->logError('symfonyBundleAdd', $e);
        }
    }

    public function symfonyAddServiceToYaml(
        string $serviceClass,
        ?array $arguments = null,
        ?bool $public = null,
        ?string $decorates = null,
        ?array $tags = null,
    ): void {
        try {
            echo "    - Adding service $serviceClass to symfony services.yaml." . PHP_EOL;
            SystemValidator::validateSystem($this->packageInfo, KnownSystemsEnum::SYMFONY);

            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: 'config/services.yaml',
            );

            $modifiedContent = SymfonyModifier::addServiceToYaml(
                fileContent: FileResolver::loadFile($file),
                serviceClass: $serviceClass,
                arguments: $arguments,
                public: $public,
                decorates: $decorates,
                tags: $tags,
            );

            FileResolver::storeFileModification($file, $modifiedContent);

        } catch (Throwable $e) {
            $this->logError('symfonyAddServiceToYaml', $e);
        }
    }

    /**
     * @param array<string|int, string|EnvVar> $entries
     */
    public function envAdd(EnvTargetEnum $target, array $entries, bool $overwrite = false): void
    {
        if (!is_array($entries)) {
            $entries = [$entries];
        }
        foreach ($entries as $key => $entry) {
            if (!$entry instanceof EnvVar) {
                $entries[$key] = new EnvVar(
                    name: (string)$key,
                    value: (string)$entry,
                );
            }
        }
        $entries = array_values($entries); // reindex numerically for the modifier

        try {
            echo "    - Adding " . count($entries) . " entries to " . $target->value . ":" . PHP_EOL;
            SystemValidator::validateSystem($this->packageInfo, $target->getSystem());
            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: $target->value,
                createIfNotExists: true,
            );

            $modifiedContent = EnvModifier::add(
                fileContent: FileResolver::loadFile($file),
                entries: $entries,
                groupName: $this->packageInfo->getNamespace(),
                overwrite: $overwrite,
            );

            FileResolver::storeFileModification($file, $modifiedContent);

        } catch (Throwable $e) {
            var_dump($e);
            $this->logError('envAdd', $e);
        }
    }

}
