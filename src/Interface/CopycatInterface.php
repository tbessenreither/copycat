<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Interface;

use Tbessenreither\Copycat\Dto\EnvVar;
use Tbessenreither\Copycat\Enum\CopyTargetEnum;
use Tbessenreither\Copycat\Enum\EnvTargetEnum;
use Tbessenreither\Copycat\Enum\JsonTargetEnum;

interface CopycatInterface
{
    /**
     * Copies a file from the package to the specified target location in the project.
     * This method does not create directories if they do not exist, so the target directory must already exist before calling this method.
     */
    public function copy(CopyTargetEnum $target, string $file, bool $overwrite = true, bool $gitIgnore = false, bool $createTargetDirectory = false): void;

    public function jsonAdd(JsonTargetEnum $target, string $path, mixed $value, bool $overwrite = false): void;

    /**
     * Adds one or more entries to the .gitignore file in project root. If the .gitignore file does not exist, it will be created.
     * @param string|string[] $entry
     */
    public function gitIgnoreAdd(string|array $entries): void;

    public function symfonyBundleAdd(string $bundleClassName): void;

    public function symfonyAddServiceToYaml(string $serviceClass, array $arguments = []): void;

    /**
     * @param array<string|int, string|EnvVar> $entries
     */
    public function envAdd(EnvTargetEnum $target, array $entries, bool $overwrite = false): void;

}
