<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

use InvalidArgumentException;
use Tbessenreither\Copycat\Dto\EnvVar;
use Tbessenreither\Copycat\Dto\PackageInfo;
use Tbessenreither\Copycat\Enum\CopyTargetEnum;
use Tbessenreither\Copycat\Enum\EnvTargetEnum;
use Tbessenreither\Copycat\Enum\JsonTargetEnum;
use Tbessenreither\Copycat\Enum\KnownSystemsEnum;
use Tbessenreither\Copycat\Exception\SystemCheckFailedException;
use Tbessenreither\Copycat\Interface\CopycatInterface;
use Tbessenreither\Copycat\Modifier\EnvModifier;
use Tbessenreither\Copycat\Modifier\FileCopy;
use Tbessenreither\Copycat\Modifier\IgnoreFileModifier;
use Tbessenreither\Copycat\Modifier\JsonModifier;
use Tbessenreither\Copycat\Modifier\SymfonyModifier;
use Throwable;

/**
 * Coordinates the per-package operations that make up a Copycat run.
 *
 * Each public op method (copy, envAdd, jsonAdd, …) does the actual work
 * (system validation, file resolution, modifier invocation) and then hands
 * the outcome to {@see OperationRecorder} instead of printing anything
 * directly. Presentation is deferred until {@see self::flush()}, which the
 * Runner calls after each package's {@see \Tbessenreither\Copycat\Interface\CopycatConfigInterface::run()}.
 *
 * The two collaborators involved:
 *
 *   - {@see OperationRecorder} — appends one entry per op call and, on
 *     {@see OperationRecorder::drain()}, coalesces consecutive entries that
 *     share a target file/directory into a single group.
 *   - {@see OperationPrinter}  — takes the coalesced groups and renders them
 *     to {@see ConsoleOutput}, applying the NORMAL/VERBOSE layout rules
 *     documented in the README under "Verbosity".
 *
 * If the printer reports that nothing was emitted for the package (every
 * group was a benign skip at NORMAL), this class prints `(no changes)` so
 * an empty section under the package heading doesn't look like something
 * went wrong.
 */
class Copycat extends CopycatBase implements CopycatInterface
{
    private readonly OperationRecorder $recorder;
    private readonly OperationPrinter $printer;

    public function __construct(
        PackageInfo $packageInfo,
        ?string $projectRoot = null,
        ?OperationRecorder $recorder = null,
        ?OperationPrinter $printer = null,
    ) {
        parent::__construct($packageInfo, $projectRoot);
        $this->recorder = $recorder ?? new OperationRecorder();
        $this->printer = $printer ?? new OperationPrinter();
    }

    /**
     * Copies a file from the package to the specified target location in the project.
     * This method does not create directories if they do not exist, so the target directory must already exist before calling this method.
     */
    public function copy(CopyTargetEnum $target, string $file, bool $overwrite = true, bool $gitIgnore = false, bool $createTargetDirectory = false): void
    {
        try {
            ConsoleOutput::debug(sprintf("• Check file copy %s to %s/ ...", basename($file), $target->value), 1);
            SystemValidator::validateSystem($this->packageInfo, $target->getSystem());

            $file = FileResolver::resolve(
                packageInfo: $this->packageInfo,
                file: $file,
            );

            $wasCopied = FileCopy::copy(
                source: $file,
                destinationDirectory: $this->getTargetDir($target),
                overwrite: $overwrite,
                createTargetDirectory: $createTargetDirectory,
            );

            $this->recorder->recordCopy($target->value, basename($file), $wasCopied);

            if ($gitIgnore) {
                $gitignoreValue = $target->value . '/' . basename($file);
                if (str_starts_with($gitignoreValue, './')) {
                    $gitignoreValue = substr($gitignoreValue, 2);
                }
                $this->gitIgnoreAdd($gitignoreValue);
            }

        } catch (SystemCheckFailedException $e) {
            ConsoleOutput::verbose($e->getMessage(), 2);
        } catch (Throwable $e) {
            ConsoleOutput::error($e->getMessage(), 2);
        }
    }

    public function copyDirectory(CopyTargetEnum $target, string $source, bool $overwrite = true, bool $gitIgnore = false, bool $createTargetDirectory = false): void
    {
        try {
            ConsoleOutput::debug(sprintf("• Copy directory %s to %s/ ...", basename($source), $target->value), 1);
            SystemValidator::validateSystem($this->packageInfo, $target->getSystem());

            $sourceDir = FileResolver::resolveDirectory(
                packageInfo: $this->packageInfo,
                directory: $source,
            );

            if (!is_dir($sourceDir)) {
                throw new InvalidArgumentException('Source directory does not exist: ' . $sourceDir);
            }

            $destinationDir = $this->getTargetDir($target);

            $result = FileCopy::copyDirectory(
                sourceDirectory: $sourceDir,
                destinationDirectory: $destinationDir,
                overwrite: $overwrite,
                createTargetDirectory: $createTargetDirectory,
            );

            foreach ($result['copied'] as $relativePath) {
                $this->recorder->recordCopy($target->value, $relativePath, true);
            }
            foreach ($result['skipped'] as $relativePath) {
                $this->recorder->recordCopy($target->value, $relativePath, false);
            }

        } catch (Throwable $e) {
            $this->logError('copyDirectory', $e);
        }
    }

    public function jsonAdd(JsonTargetEnum $target, string $path, mixed $value, bool $overwrite = false): void
    {
        try {
            JsonModifier::securityChecks(target: $target, path: $path);
            SystemValidator::validateSystem($this->packageInfo, $target->getSystem());

            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: $target->value,
            );

            $result = JsonModifier::add(
                fileContent: FileResolver::loadFile($file),
                path: $path,
                value: $value,
                overwrite: $overwrite,
            );

            FileResolver::storeFileModification($file, $result['content']);

            $this->recorder->recordJson($target->value, $path, $result['changed']);

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
        $this->ignoreFileAdd(
            method: 'gitIgnoreAdd',
            fileName: '.gitignore',
            system: KnownSystemsEnum::GIT,
            entries: $entries,
        );
    }

    /**
     * Adds one or more entries to the .dockerignore file in project root. If the .dockerignore file does not exist, it will be created.
     * Only runs in projects that contain a Dockerfile.
     * @param string|string[] $entries
     * @return void
     */
    public function dockerIgnoreAdd(string|array $entries): void
    {
        $this->ignoreFileAdd(
            method: 'dockerIgnoreAdd',
            fileName: '.dockerignore',
            system: KnownSystemsEnum::DOCKER,
            entries: $entries,
        );
    }

    /**
     * @param string|string[] $entries
     */
    private function ignoreFileAdd(string $method, string $fileName, KnownSystemsEnum $system, string|array $entries): void
    {
        if (!is_array($entries)) {
            $entries = [$entries];
        }
        try {
            SystemValidator::validateSystem($this->packageInfo, $system);
            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: $fileName,
                createIfNotExists: true,
            );

            $result = IgnoreFileModifier::add(
                fileContent: FileResolver::loadFile($file),
                entries: $entries,
                groupName: $this->packageInfo->getNamespace(),
                fileName: $fileName,
            );

            FileResolver::storeFileModification($file, $result['content']);

            $this->recorder->recordIgnore($fileName, $result['added'], $result['skipped']);

        } catch (Throwable $e) {
            $this->logError($method, $e);
        }
    }

    public function symfonyBundleAdd(string $bundleClassName): void
    {
        try {
            SystemValidator::validateSystem($this->packageInfo, KnownSystemsEnum::SYMFONY);

            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: 'config/bundles.php',
            );

            $result = SymfonyModifier::addToBundle(
                fileContent: FileResolver::loadFile($file),
                bundleClassName: $bundleClassName,
            );

            FileResolver::storeFileModification($file, $result['content']);

            $this->recorder->recordBundle('config/bundles.php', $bundleClassName, $result['changed']);

        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
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
            SystemValidator::validateSystem($this->packageInfo, KnownSystemsEnum::SYMFONY);

            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: 'config/services.yaml',
            );

            $result = SymfonyModifier::addServiceToYaml(
                fileContent: FileResolver::loadFile($file),
                serviceClass: $serviceClass,
                arguments: $arguments,
                public: $public,
                decorates: $decorates,
                tags: $tags,
            );

            FileResolver::storeFileModification($file, $result['content']);

            $this->recorder->recordService('config/services.yaml', $serviceClass, $result['changed']);

        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    /**
     * @param array<string|int, string|EnvVar> $entries
     */
    public function envAdd(EnvTargetEnum $target, array $entries, bool $overwrite = false): void
    {
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
            SystemValidator::validateSystem($this->packageInfo, $target->getSystem());
            $file = FileResolver::resolveInProject(
                packageInfo: $this->packageInfo,
                file: $target->value,
                createIfNotExists: true,
            );

            $result = EnvModifier::add(
                fileContent: FileResolver::loadFile($file),
                entries: $entries,
                groupName: $this->packageInfo->getNamespace(),
                overwrite: $overwrite,
            );

            FileResolver::storeFileModification($file, $result['content']);

            $this->recorder->recordEnv(
                file: $target->value,
                added: $result['added'],
                replaced: $result['replaced'],
                skipped: $result['skipped'],
            );

        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    /**
     * Flush all buffered operations for the current package as grouped output.
     *
     * Hands the recorder's coalesced groups to the printer. If nothing was
     * user-visible (e.g. every group was a benign skip at NORMAL), emits a
     * fallback `(no changes)` line so an empty package section doesn't look
     * like something went wrong.
     */
    public function flush(): void
    {
        if (!$this->printer->emit($this->recorder->drain())) {
            ConsoleOutput::info('<dim>(no changes)</dim>', 1);
        }
    }
}
