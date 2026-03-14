<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

use Tbessenreither\Copycat\Enum\CopyTargetEnum;

class ConfigLoader
{
    public const string CONFIG_FILE_NAME = 'config.json';
    public static $configCache = null;

    public static function getWhitelistedNamespaces(): array|false
    {
        $config = self::getCopycatConfig();
        if (
            !isset($config['whitelistedNamespaces'])
            || !is_array($config['whitelistedNamespaces'])
        ) {
            return false;
        }

        return $config['whitelistedNamespaces'];

    }

    public static function getCopycatConfig(): array
    {
        $rootComposerJsonPath = FileResolver::getProjectRootDir() . DIRECTORY_SEPARATOR . 'composer.json';
        if (is_file($rootComposerJsonPath)) {
            $composerContent = file_get_contents($rootComposerJsonPath);
            $composerData = json_decode($composerContent, true);
            if (
                isset($composerData['extra']) && is_array($composerData['extra'])
                && isset($composerData['extra']['copycat']) && is_array($composerData['extra']['copycat'])
            ) {
                echo "Loaded config from composer.json at extra.copycat.\n";

                return $composerData['extra']['copycat'];
            }
        }

        $possibleConfigFiles = [
            FileResolver::getProjectRootDir() . DIRECTORY_SEPARATOR . CopyTargetEnum::COPYCAT_CONFIG->value . DIRECTORY_SEPARATOR . ConfigLoader::CONFIG_FILE_NAME,
            realpath(__DIR__ . '/../../config/' . ConfigLoader::CONFIG_FILE_NAME),
        ];

        $resolvedFile = FileResolver::resolveFileByPriority($possibleConfigFiles);

        $configContent = file_get_contents($resolvedFile);
        $configContentDecoded = json_decode($configContent, true);

        echo "Loaded config from file: " . $resolvedFile . "\n";

        return $configContentDecoded;
    }

}
