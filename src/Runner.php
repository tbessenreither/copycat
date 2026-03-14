<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat;

use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\Installer\PackageEvent;
use Composer\Script\Event;
use ReflectionClass;
use Tbessenreither\Copycat\Interface\CopycatConfigInterface;
use Tbessenreither\Copycat\Service\Copycat;
use Tbessenreither\Copycat\Service\CopycatReverse;
use Tbessenreither\Copycat\Service\FileResolver;
use Tbessenreither\Copycat\Service\NamespaceCrawler;

class Runner
{
    public static function run(Event|PackageEvent $event): void
    {
        echo str_repeat(PHP_EOL, 2);
        echo "Running PHP Copycat..." . PHP_EOL;
        if ($event instanceof Event) {
            self::onInstallOrUpdate();
        } elseif ($event instanceof PackageEvent) {
            foreach ($event->getOperations() as $operation) {
                if ($operation->getOperationType() === 'uninstall') {
                    self::onUninstall($operation);
                } else {
                    echo "Operation type " . $operation->getOperationType() . " not supported." . PHP_EOL;
                }
            }
        }
        FileResolver::writeBufferedFilesToDisk();
        echo PHP_EOL . "PHP Copycat finished." . PHP_EOL;
        echo str_repeat(PHP_EOL, 2);
    }

    private static function onUninstall(OperationInterface $operation): void
    {
        $packageInfoString = $operation->show(false);
        //get string between <info> and </info>
        preg_match('/<info>(.*?)<\/info>/', $packageInfoString, $matches);
        if (count($matches) < 2) {
            echo "Could not extract package info from string: " . $packageInfoString . PHP_EOL;

            return;
        }
        $packageInfoStringCleaned = $matches[1];
        $packageInfoStringCleaned = trim($packageInfoStringCleaned);

        $namespaces = NamespaceCrawler::getPackageInfos();
        $packageInfo = null;
        foreach ($namespaces as $namespace) {
            if ($namespace->getComposerName() === $packageInfoStringCleaned) {
                $packageInfo = $namespace;

                break;
            }
        }
        if ($packageInfo === null) {
            echo "Could not find affected namespace for package: " . $packageInfoStringCleaned . PHP_EOL;

            return;
        }

        $copycatInstance = new CopycatReverse(
            packageInfo: $packageInfo,
        );
        $copycatClass = $packageInfo->getNamespace() . '\\CopycatConfig';

        if (!class_exists($copycatClass)) {
            echo "No CopycatConfig class found for namespace " . $packageInfo->getNamespace() . ", skipping uninstall operations." . PHP_EOL;

            return;
        }

        $reflectionClass = new ReflectionClass($copycatClass);
        if (!$reflectionClass->implementsInterface(CopycatConfigInterface::class)) {
            echo "CopycatConfig class for namespace " . $packageInfo->getNamespace() . " does not implement CopycatConfigInterface, skipping uninstall operations." . PHP_EOL;

            return;
        }

        echo "Reverting copycat for namespace " . $packageInfo->getNamespace() . PHP_EOL;
        $copycatClass::run($copycatInstance);
    }

    private static function onInstallOrUpdate(): void
    {
        $namespaces = NamespaceCrawler::getPackageInfos();

        foreach ($namespaces as $packageInfo) {
            $copycatInstance = new Copycat(
                packageInfo: $packageInfo,
            );

            $copycatClass = $packageInfo->getNamespace() . '\\CopycatConfig';

            if (!class_exists($copycatClass)) {
                continue;
            }

            $reflectionClass = new ReflectionClass($copycatClass);
            if (!$reflectionClass->implementsInterface(CopycatConfigInterface::class)) {
                continue;
            }

            echo "Running copycat for namespace " . $packageInfo->getNamespace() . PHP_EOL;
            $copycatClass::run($copycatInstance);
        }
    }

}
