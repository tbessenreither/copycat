<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

use InvalidArgumentException;
use Tbessenreither\Copycat\Dto\EnvVar;
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
 * All user-facing output goes through {@see self::flush()} at the end of a
 * package's `CopycatConfig::run()`, not at operation call time. Every op
 * appends a record to an internal timeline; consecutive records that target
 * the same file/directory coalesce into a single summary. The Runner is
 * responsible for calling {@see self::flush()} after each package.
 *
 * Layout rules for the summary lines are documented in the README under
 * "Verbosity" and in `drafts.local/copycat-review/README.txt` — briefly:
 *
 *   - Env vars: file heading + per-name adds/replaces; skips collapse to
 *     a count at NORMAL, expand to `⏭ NAME` lines at VERBOSE.
 *   - Copy:     grouped by target directory; inline when ≤ 5 items and the
 *               rendered line is ≤ 100 chars, otherwise `— N copied` at
 *               NORMAL / per-name list at VERBOSE.
 *   - Ignore:   per-file summary (`— N added`); zero-add cases silent at
 *               NORMAL, surfaced at VERBOSE.
 *   - JSON:     single line per path, silent-on-skip at NORMAL.
 *   - Symfony:  single line per class, silent-on-skip at NORMAL.
 */
class Copycat extends CopycatBase implements CopycatInterface
{
    /** Threshold for the inline-when-short rule (character budget, indent included). */
    private const int INLINE_MAX_LINE_LENGTH = 100;

    /** Threshold for the inline-when-short rule (max entries). */
    private const int INLINE_MAX_ENTRIES = 5;

    /**
     * Ordered list of operations recorded during this package's run.
     * Flushed and cleared by {@see self::flush()}.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $ops = [];

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

            $this->recordCopy($target->value, basename($file), $wasCopied);

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
                $this->recordCopy($target->value, $relativePath, true);
            }
            foreach ($result['skipped'] as $relativePath) {
                $this->recordCopy($target->value, $relativePath, false);
            }

        } catch (Throwable $e) {
            $this->logError('copyDirectory', $e);
        }
    }

    public function jsonAdd(JsonTargetEnum $target, string $path, mixed $value, bool $overwrite = false): void
    {
        try {
            ConsoleOutput::verbose(sprintf("• Adding value to %s at path %s", $target->value, $path), 1);

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

            $this->recordOp('json', [
                'file' => $target->value,
                'path' => $path,
                'changed' => $result['changed'],
            ]);

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
            ConsoleOutput::verbose(sprintf("• Checking %d line%s for %s", count($entries), count($entries) === 1 ? '' : 's', $fileName), 1);
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

            $this->recordOp('ignore', [
                'file' => $fileName,
                'added' => $result['added'],
                'skipped' => $result['skipped'],
            ]);

        } catch (Throwable $e) {
            $this->logError($method, $e);
        }
    }

    public function symfonyBundleAdd(string $bundleClassName): void
    {
        try {
            ConsoleOutput::verbose(sprintf("• Adding %s to symfony bundles.php", $bundleClassName), 1);
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

            $this->recordOp('bundle', [
                'file' => 'config/bundles.php',
                'identifier' => $bundleClassName,
                'changed' => $result['changed'],
            ]);

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
            ConsoleOutput::verbose(sprintf("• Adding service %s to symfony services.yaml", $serviceClass), 1);
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

            $this->recordOp('service', [
                'file' => 'config/services.yaml',
                'identifier' => $serviceClass,
                'changed' => $result['changed'],
            ]);

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
            ConsoleOutput::verbose(sprintf("• Checking %d env var%s for %s", count($entries), count($entries) === 1 ? '' : 's', $target->value), 1);
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

            $this->recordOp('env', [
                'file' => $target->value,
                'added' => $result['added'],
                'replaced' => $result['replaced'],
                'skipped' => $result['skipped'],
            ]);

        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    // ------------------------------------------------------------------
    // Timeline & flushing
    // ------------------------------------------------------------------

    /**
     * Flush all buffered operations for the current package as grouped output.
     *
     * Coalesces consecutive operations against the same target (env file, copy
     * directory, ignore file) into single summary lines, then renders each
     * group according to its type's layout rules. If no group produces any
     * user-visible line, emits "(no changes)" so an empty package section
     * doesn't render as just a heading.
     */
    public function flush(): void
    {
        $groups = $this->coalesce($this->ops);

        $emittedAny = false;
        foreach ($groups as $group) {
            $emittedSomething = $this->emitGroup($group);
            $emittedAny = $emittedAny || $emittedSomething;
        }

        if (!$emittedAny) {
            ConsoleOutput::info('<dim>(no changes)</dim>', 1);
        }

        $this->ops = [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function recordOp(string $type, array $payload): void
    {
        $this->ops[] = array_merge(['type' => $type], $payload);
    }

    private function recordCopy(string $targetDir, string $sourceName, bool $wasCopied): void
    {
        $this->ops[] = [
            'type' => 'copy',
            'targetDir' => $targetDir,
            'added' => $wasCopied ? [$sourceName] : [],
            'skipped' => $wasCopied ? [] : [$sourceName],
        ];
    }

    /**
     * Merge every op that shares a coalescence key into a single group entry,
     * preserving the position of the group's first occurrence.
     *
     * @param array<int, array<string, mixed>> $ops
     * @return array<int, array<string, mixed>>
     */
    private function coalesce(array $ops): array
    {
        /** @var array<string, int> $keyToIndex */
        $keyToIndex = [];
        $groups = [];

        foreach ($ops as $op) {
            $key = $this->coalesceKey($op);

            if (isset($keyToIndex[$key])) {
                $idx = $keyToIndex[$key];
                $groups[$idx] = $this->mergeGroup($groups[$idx], $op);
            } else {
                $keyToIndex[$key] = count($groups);
                $groups[] = $op;
            }
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $op
     */
    private function coalesceKey(array $op): string
    {
        return match ($op['type']) {
            'env' => 'env:' . $op['file'],
            'copy' => 'copy:' . $op['targetDir'],
            'ignore' => 'ignore:' . $op['file'],
            // json/bundle/service are keyed by their identifier so consecutive
            // identical calls (unusual, but possible) collapse into one line.
            'json' => 'json:' . $op['file'] . ':' . $op['path'],
            'bundle' => 'bundle:' . $op['file'] . ':' . $op['identifier'],
            'service' => 'service:' . $op['file'] . ':' . $op['identifier'],
            default => 'unknown:' . spl_object_hash((object) $op),
        };
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    private function mergeGroup(array $a, array $b): array
    {
        foreach (['added', 'replaced', 'skipped'] as $field) {
            if (isset($b[$field])) {
                $a[$field] = array_merge($a[$field] ?? [], $b[$field]);
            }
        }
        if (isset($b['changed'])) {
            $a['changed'] = ($a['changed'] ?? false) || $b['changed'];
        }
        return $a;
    }

    /**
     * @param array<string, mixed> $group
     * @return bool `true` when the group produced at least one visible line.
     */
    private function emitGroup(array $group): bool
    {
        return match ($group['type']) {
            'env' => $this->emitEnv($group),
            'copy' => $this->emitCopy($group),
            'ignore' => $this->emitIgnore($group),
            'json' => $this->emitJson($group),
            'bundle' => $this->emitBundle($group),
            'service' => $this->emitService($group),
            default => false,
        };
    }

    // ------------------------------------------------------------------
    // Per-type emit methods
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $group
     */
    private function emitEnv(array $group): bool
    {
        $file = (string) $group['file'];
        /** @var string[] $added */
        $added = $group['added'] ?? [];
        /** @var string[] $replaced */
        $replaced = $group['replaced'] ?? [];
        /** @var string[] $skipped */
        $skipped = $group['skipped'] ?? [];

        if (ConsoleOutput::isVerbose()) {
            $this->emitFileHeading($file);
            foreach ($added as $name) {
                ConsoleOutput::info('+ ' . $name, 2);
            }
            foreach ($replaced as $name) {
                ConsoleOutput::info('↻ ' . $name, 2);
            }
            foreach ($skipped as $name) {
                ConsoleOutput::info('<dim>⏭ ' . $name . '</dim>', 2);
            }
            return true;
        }

        $activeCount = count($added) + count($replaced);
        $skippedCount = count($skipped);

        if ($activeCount === 0) {
            if ($skippedCount === 0) {
                return false;
            }
            ConsoleOutput::info(
                sprintf('<dim>•</dim> %s: %d already set — skipped', $file, $skippedCount),
                1,
            );
            return true;
        }

        // Try inline first.
        $items = [];
        foreach ($added as $name) {
            $items[] = '+ ' . $name;
        }
        foreach ($replaced as $name) {
            $items[] = '↻ ' . $name;
        }
        $itemsStr = implode(', ', $items);
        $skipSuffix = $skippedCount > 0 ? sprintf(' (%d already set)', $skippedCount) : '';
        $inlinePayload = sprintf('%s: %s%s', $file, $itemsStr, $skipSuffix);

        if ($this->fitsInline($activeCount, $inlinePayload)) {
            ConsoleOutput::info('<dim>•</dim> ' . $inlinePayload, 1);
            return true;
        }

        // Multiline fallback at NORMAL.
        $this->emitFileHeading($file);
        foreach ($added as $name) {
            ConsoleOutput::info('+ ' . $name, 2);
        }
        foreach ($replaced as $name) {
            ConsoleOutput::info('↻ ' . $name, 2);
        }
        if ($skippedCount > 0) {
            ConsoleOutput::info(
                sprintf('<dim>(%d already set — skipped)</dim>', $skippedCount),
                2,
            );
        }
        return true;
    }

    /**
     * @param array<string, mixed> $group
     */
    private function emitCopy(array $group): bool
    {
        $targetDir = rtrim((string) $group['targetDir'], '/') . '/';
        /** @var string[] $added */
        $added = $group['added'] ?? [];
        /** @var string[] $skipped */
        $skipped = $group['skipped'] ?? [];
        $addedCount = count($added);
        $skippedCount = count($skipped);

        if (ConsoleOutput::isVerbose()) {
            if ($addedCount === 0 && $skippedCount === 0) {
                return false;
            }
            $this->emitFileHeading($targetDir);
            foreach ($added as $name) {
                ConsoleOutput::info('+ ' . $name, 2);
            }
            if ($skippedCount > 0) {
                ConsoleOutput::info(
                    sprintf(
                        '<dim>(%d already %s — skipped)</dim>',
                        $skippedCount,
                        $skippedCount === 1 ? 'exists' : 'exist',
                    ),
                    2,
                );
            }
            return true;
        }

        // NORMAL: silent when nothing was actually copied.
        if ($addedCount === 0) {
            return false;
        }

        $inlinePayload = sprintf('%s: %s', $targetDir, implode(', ', $added));
        if ($this->fitsInline($addedCount, $inlinePayload)) {
            ConsoleOutput::info('<dim>•</dim> ' . $inlinePayload, 1);
            return true;
        }

        ConsoleOutput::info(
            sprintf('<dim>•</dim> %s — %d copied', $targetDir, $addedCount),
            1,
        );
        return true;
    }

    /**
     * @param array<string, mixed> $group
     */
    private function emitIgnore(array $group): bool
    {
        $file = (string) $group['file'];
        /** @var string[] $added */
        $added = $group['added'] ?? [];
        /** @var string[] $skipped */
        $skipped = $group['skipped'] ?? [];
        $addedCount = count($added);
        $skippedCount = count($skipped);

        if (ConsoleOutput::isVerbose()) {
            if ($addedCount === 0 && $skippedCount === 0) {
                return false;
            }
            if ($addedCount === 0) {
                ConsoleOutput::info(
                    sprintf(
                        '<dim>•</dim> %s — 0 added, %d already present',
                        $file,
                        $skippedCount,
                    ),
                    1,
                );
                return true;
            }
            $this->emitFileHeading($file);
            foreach ($added as $entry) {
                ConsoleOutput::info('+ ' . $entry, 2);
            }
            if ($skippedCount > 0) {
                ConsoleOutput::info(
                    sprintf('<dim>(%d already present)</dim>', $skippedCount),
                    2,
                );
            }
            return true;
        }

        // NORMAL: silent when no adds.
        if ($addedCount === 0) {
            return false;
        }
        ConsoleOutput::info(
            sprintf('<dim>•</dim> %s — %d added', $file, $addedCount),
            1,
        );
        return true;
    }

    /**
     * @param array<string, mixed> $group
     */
    private function emitJson(array $group): bool
    {
        $file = (string) $group['file'];
        $path = (string) $group['path'];
        $changed = (bool) ($group['changed'] ?? false);

        if (!$changed) {
            if (ConsoleOutput::isVerbose()) {
                $this->emitFileHeading($file);
                ConsoleOutput::info(sprintf('<dim>⏭ %s (already present)</dim>', $path), 2);
                return true;
            }
            return false;
        }

        ConsoleOutput::info(
            sprintf('<dim>•</dim> %s: added %s', $file, $path),
            1,
        );
        return true;
    }

    /**
     * @param array<string, mixed> $group
     */
    private function emitBundle(array $group): bool
    {
        $file = (string) $group['file'];
        $identifier = (string) $group['identifier'];
        $changed = (bool) ($group['changed'] ?? false);

        if (!$changed) {
            if (ConsoleOutput::isVerbose()) {
                $this->emitFileHeading($file);
                ConsoleOutput::info(sprintf('<dim>⏭ %s (already registered)</dim>', $identifier), 2);
                return true;
            }
            return false;
        }

        ConsoleOutput::info(
            sprintf('<dim>•</dim> %s: + %s', $file, $identifier),
            1,
        );
        return true;
    }

    /**
     * @param array<string, mixed> $group
     */
    private function emitService(array $group): bool
    {
        $file = (string) $group['file'];
        $identifier = (string) $group['identifier'];

        // addServiceToYaml always mutates the YAML — no skip branch to render.
        ConsoleOutput::info(
            sprintf('<dim>•</dim> %s: + %s', $file, $identifier),
            1,
        );
        return true;
    }

    // ------------------------------------------------------------------
    // Layout helpers
    // ------------------------------------------------------------------

    private function emitFileHeading(string $file): void
    {
        ConsoleOutput::info('<dim>•</dim> ' . $file, 1);
    }

    /**
     * Decide whether the inline form fits according to the shared rule:
     * `≤ INLINE_MAX_ENTRIES entries AND rendered line ≤ INLINE_MAX_LINE_LENGTH chars`.
     * The payload should not include the leading `• ` bullet or the indent —
     * those are added by the caller and factored into the budget here.
     */
    private function fitsInline(int $entryCount, string $payload): bool
    {
        if ($entryCount > self::INLINE_MAX_ENTRIES) {
            return false;
        }
        // 4 spaces of indent (level 1) + "• " visible prefix.
        $prefixLen = 4 + 2;
        return $prefixLen + mb_strlen($payload) <= self::INLINE_MAX_LINE_LENGTH;
    }

}
