<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

use Tbessenreither\Copycat\Dto\PackageInfo;

class NamespaceCrawler
{
    /**
     * @return PackageInfo[]
     */
    public static function getPackageInfos(): array
    {
        $projectRootDir = FileResolver::getProjectRootDir();
        $vendorDir = $projectRootDir . '/vendor';

        $whitelistedNamespaces = ConfigLoader::getWhitelistedNamespaces();

        //itterate over all folders in vendor, read the composer.json and extract the namespaces from the autoload section
        $namespaces = [];
        foreach (scandir($vendorDir) as $vendorFolder) {
            if (
                $vendorFolder === '.'
                || $vendorFolder === '..'
                || !is_dir($vendorDir . '/' . $vendorFolder)
            ) {
                continue;
            }

            foreach (scandir($vendorDir . '/' . $vendorFolder) as $packageFolder) {
                if (
                    $packageFolder === '.'
                    || $packageFolder === '..'
                    || !is_dir($vendorDir . '/' . $vendorFolder . '/' . $packageFolder)
                ) {
                    continue;
                }
                $packageFolder = rtrim($packageFolder, '/');

                $packageDirectory = $vendorDir . '/' . $vendorFolder . '/' . $packageFolder;
                $packageDirectory = rtrim($packageDirectory, '/');

                $composerFile = $packageDirectory . '/composer.json';

                if (!file_exists($composerFile)) {
                    echo "No composer.json found in " . $composerFile . PHP_EOL;

                    continue;
                }

                $composerContent = file_get_contents($composerFile);
                $composerContentDecoded = json_decode($composerContent, true);
                if (
                    !isset($composerContentDecoded['autoload'])
                    || !isset($composerContentDecoded['autoload']['psr-4'])
                ) {
                    continue;
                }

                foreach ($composerContentDecoded['autoload']['psr-4'] as $namespace => $path) {
                    $namespace = rtrim($namespace, '\\');
                    if (is_array($path)) {
                        if (count($path) === 1) {
                            $path = $path[0];
                        } else {
                            echo "Multiple paths for namespace " . $namespace . " in package " . $vendorFolder . '/' . $packageFolder . PHP_EOL;

                            continue;
                        }
                    }
                    $path = rtrim($path, '/');

                    $copycatClass = $namespace . '\CopycatConfig';

                    if (!class_exists($copycatClass)) {
                        continue;
                    }

                    if ($whitelistedNamespaces !== false) {
                        foreach ($whitelistedNamespaces as $whitelistedNamespace) {
                            $whitelistMatch = false;
                            if (str_starts_with($namespace, $whitelistedNamespace)) {
                                $whitelistMatch = true;

                                break;
                            }
                        }
                        if (!$whitelistMatch) {
                            echo "    Namespace " . $namespace . " is not in the whitelist, skipping." . PHP_EOL;

                            continue;
                        }
                    }

                    $namespaces[] = new PackageInfo(
                        namespace: $namespace,
                        projectPath: $projectRootDir,
                        autoloadPath: $packageDirectory . '/' . $path,
                        packagePath: $packageDirectory,
                        composerName: $vendorFolder . '/' . $packageFolder,
                    );
                }
            }
        }

        return $namespaces;
    }

}
