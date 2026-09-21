<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat;

use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\Installer\PackageEvent;
use Composer\Script\Event;
use ReflectionClass;
use Tbessenreither\Copycat\Interface\CopycatConfigInterface;
use Tbessenreither\Copycat\Service\ConsoleOutput;
use Tbessenreither\Copycat\Service\Copycat;
use Tbessenreither\Copycat\Service\CopycatReverse;
use Tbessenreither\Copycat\Service\FileResolver;
use Tbessenreither\Copycat\Service\NamespaceCrawler;

class Runner
{
    public static function run(Event|PackageEvent $event): void
    {
        ConsoleOutput::success("Running PHP Copycat...", 0);
        if ($event instanceof Event) {
            self::onInstallOrUpdate();
        } elseif ($event instanceof PackageEvent) {
            foreach ($event->getOperations() as $operation) {
                if ($operation->getOperationType() === 'uninstall') {
                    self::onUninstall($operation);
                } else {
                    ConsoleOutput::warning("Operation type " . $operation->getOperationType() . " not supported.", 0);
                }
            }
        }
        FileResolver::writeBufferedFilesToDisk();
        ConsoleOutput::newline();
        ConsoleOutput::success("PHP Copycat finished.");
    }

    private static function onUninstall(OperationInterface $operation): void
    {
        $packageInfoString = $operation->show(false);
        //get string between <info> and </info>
        preg_match('/<info>(.*?)<\/info>/', $packageInfoString, $matches);
        if (count($matches) < 2) {
            ConsoleOutput::warning("Could not extract package info from string: " . $packageInfoString);

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
            ConsoleOutput::debug("Could not find affected namespace for package: " . $packageInfoStringCleaned);

            return;
        }

        $copycatInstance = new CopycatReverse(
            packageInfo: $packageInfo,
        );
        $copycatClass = $packageInfo->getNamespace() . '\\CopycatConfig';

        if (!class_exists($copycatClass)) {
            ConsoleOutput::debug("No CopycatConfig class found for namespace " . $packageInfo->getNamespace() . ", skipping uninstall operations.");

            return;
        }

        $reflectionClass = new ReflectionClass($copycatClass);
        if (!$reflectionClass->implementsInterface(CopycatConfigInterface::class)) {
            ConsoleOutput::warning("CopycatConfig class for namespace " . $packageInfo->getNamespace() . " does not implement CopycatConfigInterface, skipping uninstall operations.");

            return;
        }

        ConsoleOutput::heading('📦  Reverting ' . $packageInfo->getNamespace());
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

            ConsoleOutput::heading('📦  ' . $packageInfo->getNamespace());
            /** @var CopycatConfigInterface $copycatClass */
            $copycatClass::run($copycatInstance);
            $copycatInstance->flush();
        }
    }

}
