<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Dto;

class PackageInfo
{
    public function __construct(
        private string $namespace,
        private string $projectPath,
        private string $autoloadPath,
        private string $packagePath,
        private string $composerName,
    ) {
        $this->namespace = rtrim($namespace, '\\');
        $this->projectPath = rtrim($projectPath, '/\\');
        $this->autoloadPath = rtrim($autoloadPath, '/\\');
        $this->packagePath = rtrim($packagePath, '/\\');
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getProjectPath(): string
    {
        return $this->projectPath;
    }

    public function getAutoloadPath(): string
    {
        return $this->autoloadPath;
    }

    public function getPackagePath(): string
    {
        return $this->packagePath;
    }

    public function getComposerName(): string
    {
        return $this->composerName;
    }

}
