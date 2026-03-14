<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Modifier;

use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class SymfonyModifier
{
    public static function addToBundle(string $fileContent, string $bundleClassName): string
    {
        self::checkIfBundleClassIsValid($bundleClassName);

        $lines = explode(PHP_EOL, $fileContent);


        // Find the line with "return ["
        $returnLineIndex = array_search('return [', $lines, true);
        if ($returnLineIndex === false) {
            throw new RuntimeException('Could not find "return [" in bundles.php file');
        }
        $endOfReturnLineIndex = array_search('];', $lines, true);
        if ($endOfReturnLineIndex === false) {
            throw new RuntimeException('Could not find "];" in bundles.php file');
        }

        //get the indentation from the line after "return ["
        $indentation = '';
        if (isset($lines[$returnLineIndex + 1])) {
            preg_match('/^(\s*)/', $lines[$returnLineIndex + 1], $matches);
            $indentation = $matches[1] ?? '';
        }

        $bundleLine = $indentation . $bundleClassName . "::class => ['all' => true],";
        if (in_array($bundleLine, $lines, true)) {
            throw new RuntimeException('Bundle ' . $bundleClassName . ' is already registered in bundles.php, skipping.');
        }


        //find the position between $returnLineIndex and $endOfReturnLineIndex where the new bundle should be inserted, so that the bundles are sorted alphabetically.
        $insertIndex = $returnLineIndex + 1;
        for ($i = $returnLineIndex + 1; $i < $endOfReturnLineIndex; $i++) {
            if (strcmp($lines[$i], $bundleLine) > 0) {
                $insertIndex = $i;

                break;
            }
        }
        // Insert the new bundle line at the correct position
        array_splice($lines, $insertIndex, 0, $bundleLine);

        return implode(PHP_EOL, $lines);
    }

    public static function removeFromBundle(string $fileContent, string $bundleClassName): string
    {
        $lines = explode(PHP_EOL, $fileContent);

        $bundleLine = null;
        foreach ($lines as $lineKey => $line) {
            if (strpos(trim($line), $bundleClassName . "::class") === 0) {
                unset($lines[$lineKey]);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    public static function addServiceToYaml(
        string $fileContent,
        string $serviceClass,
        ?array $arguments = null,
        ?bool $public = null,
        ?string $decorates = null,
        ?array $tags = null,
    ): string {
        $yamlLines = explode(PHP_EOL, $fileContent);

        $serviceElement = [
            'class' => $serviceClass,
        ];
        if ($public !== null) {
            $serviceElement['public'] = $public;
        }
        if ($decorates !== null) {
            $serviceElement['decorates'] = $decorates;
        }
        if ($tags !== null) {
            $serviceElement['tags'] = $tags;
        }
        if ($arguments !== null) {
            $serviceElement['arguments'] = $arguments;
        }

        $elementsToAdd = [
            $serviceClass => $serviceElement,
        ];

        $modifiedLines = YamlModifier::insertArrayIntoObject(
            lines: $yamlLines,
            insertBlockKey: 'services',
            insertArray: $elementsToAdd,
        );

        $yamlString = implode(PHP_EOL, $modifiedLines);

        return $yamlString;
    }

    private static function checkIfBundleClassIsValid(string $bundleClassName): void
    {
        if (!class_exists($bundleClassName)) {
            throw new RuntimeException('Bundle class ' . $bundleClassName . ' does not exist');
        }

        $reflectionClass = new ReflectionClass($bundleClassName);
        //check if it implements the BundleInterface
        if (!$reflectionClass->implementsInterface(BundleInterface::class)) {
            throw new RuntimeException('Bundle class ' . $bundleClassName . ' does not implement the Symfony BundleInterface. This will not be added to bundles.php');
        }
    }

}
