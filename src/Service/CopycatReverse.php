<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

use RuntimeException;
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
use Tbessenreither\Copycat\Modifier\YamlModifier;
use Throwable;

class CopycatReverse extends CopycatBase implements CopycatInterface
{
    /**
     * Copies a file from the package to the specified target location in the project.
     * This method does not create directories if they do not exist, so the target directory must already exist before calling this method.
     */
    public function copy(CopyTargetEnum $target, string $file, bool $overwrite = true, bool $gitIgnore = false, bool $createTargetDirectory = false): void
    {
        try {
            echo '    - Removing ' . $file . ' from ' . $target->value . '' . PHP_EOL;
            SystemValidator::validateSystem($this->packageInfo, $target->getSystem());

            if ($gitIgnore) {
                $this->gitIgnoreAdd($target->value . '/' . basename($file));
            }

            FileCopy::remove(
                source: $file,
                destinationDirectory: $this->getTargetDir($target),
            );

        } catch (Throwable $e) {
            $this->logError('copy', $e);
        }
    }

    public function copyDirectory(CopyTargetEnum $target, string $source, bool $overwrite = true, bool $gitIgnore = false, bool $createTargetDirectory = false): void
    {
        try {
            echo '    - Removing directory ' . $source . ' from ' . $target->value . '' . PHP_EOL;
            SystemValidator::validateSystem($this->packageInfo, $target->getSystem());

            if ($gitIgnore) {
                $this->gitIgnoreAdd($target->value . '/' . basename($source));
            }

            $sourceDir = FileResolver::resolveDirectory(
                packageInfo: $this->packageInfo,
                directory: $source,
            );

            FileCopy::removeDirectory(
                sourceDirectory: $sourceDir,
                destinationDirectory: $this->getTargetDir($target),
            );

        } catch (Throwable $e) {
            $this->logError('copyDirectory', $e);
        }
    }

    public function jsonAdd(JsonTargetEnum $target, string $path, mixed $value, bool $overwrite = false): void
    {
        try {
            echo "    - Removing value from " . $target->value . " at path " . $path . PHP_EOL;
            JsonModifier::securityChecks(target: $target, path: $path);
            SystemValidator::validateSystem($this->packageInfo, $target->getSystem());

            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: $target->value,
            );

            if (!$target->canRemoveValues()) {
                throw new RuntimeException('Removing values from ' . $target->value . ' is not allowed.');
            }

            $jsonModified = JsonModifier::remove(
                fileContent: FileResolver::loadFile($file),
                path: $path,
            );

            FileResolver::storeFileModification($file, $jsonModified);

        } catch (Throwable $e) {
            $this->logError('jsonAdd', $e);
        }
    }

    /**
     * Adds one or more entries to the .gitignore file in project root. If the .gitignore file does not exist, it will be created.
     * @param string|string[] $entries
     * @return void
     */
    public function gitIgnoreAdd(string|array $entries): void
    {
        $this->ignoreFileRemove(
            method: 'gitIgnoreAdd',
            fileName: '.gitignore',
            system: KnownSystemsEnum::GIT,
        );
    }

    /**
     * Removes this package's group from the .dockerignore file in project root.
     * @param string|string[] $entries
     * @return void
     */
    public function dockerIgnoreAdd(string|array $entries): void
    {
        $this->ignoreFileRemove(
            method: 'dockerIgnoreAdd',
            fileName: '.dockerignore',
            system: KnownSystemsEnum::DOCKER,
        );
    }

    private function ignoreFileRemove(string $method, string $fileName, KnownSystemsEnum $system): void
    {
        try {
            echo "    - Removing " . $fileName . " entries:" . PHP_EOL;
            SystemValidator::validateSystem($this->packageInfo, $system);
            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: $fileName,
                createIfNotExists: true,
            );

            $modifiedContent = GitignoreModifier::remove(
                fileContent: FileResolver::loadFile($file),
                groupName: $this->packageInfo->getNamespace(),
                fileName: $fileName,
            );

            FileResolver::storeFileModification($file, $modifiedContent);

        } catch (Throwable $e) {
            $this->logError($method, $e);
        }
    }

    public function symfonyBundleAdd(string $bundleClassName): void
    {
        try {
            echo "    - Removing $bundleClassName from symfony bundles.php." . PHP_EOL;
            SystemValidator::validateSystem($this->packageInfo, KnownSystemsEnum::SYMFONY);

            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: 'config/bundles.php',
            );

            $modifiedContent = SymfonyModifier::removeFromBundle(
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
            echo "    - Removing service $serviceClass from symfony services.yaml." . PHP_EOL;
            SystemValidator::validateSystem($this->packageInfo, KnownSystemsEnum::SYMFONY);

            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: 'config/services.yaml',
            );

            $modifiedContent = YamlModifier::cleanObjectFromLines(
                lines: explode(PHP_EOL, FileResolver::loadFile($file)),
                clearKeys: [$serviceClass],
            );
            $modifiedContentString = implode(PHP_EOL, $modifiedContent);

            FileResolver::storeFileModification($file, $modifiedContentString);

        } catch (Throwable $e) {
            $this->logError('symfonyAddServiceToYaml', $e);
        }
    }

    /**
     * @param array<string|int, string|EnvVar> $entries
     */
    public function envAdd(EnvTargetEnum $target, array $entries, bool $overwrite = false): void
    {
        try {
            echo "    - Removing environment variables for " . $this->packageInfo->getNamespace() . PHP_EOL;
            SystemValidator::validateSystem($this->packageInfo, $target->getSystem());

            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: $target->value,
            );

            if (!file_exists($file)) {
                echo "      No " . $target->value . " file found, skipping." . PHP_EOL;

                return;
            }

            $modifiedContent = EnvModifier::remove(
                fileContent: FileResolver::loadFile($file),
                groupName: $this->packageInfo->getNamespace(),
            );

            FileResolver::storeFileModification($file, $modifiedContent);

        } catch (Throwable $e) {
            $this->logError('envAdd', $e);
        }
    }

}
