<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Modifier;

use RuntimeException;

class YamlModifier
{
    public const string INDENTATION = '    ';

    public static function indentationString(int $indentLevel, int $indentSize = 4): string
    {
        return str_repeat(' ', $indentLevel * $indentSize);
    }


    public static function arrayToYaml(array $array, int $indentLevel = 0, int $indentSize = 4, bool $newlineAfterRootElements = false): string
    {
        $yaml = '';
        $arrayKeys = array_keys($array);
        $isSequential = $arrayKeys === range(0, count($array) - 1);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if ($isSequential) {
                    $yaml .= self::indentationString($indentLevel, $indentSize) . '- ' . (is_array($value) ? '{' . PHP_EOL : self::formatValue($value) . PHP_EOL);
                    $yaml .= self::arrayToYaml($value, $indentLevel + 1, $indentSize);
                    $yaml .= self::indentationString($indentLevel, $indentSize) . '  }' . PHP_EOL;
                } else {
                    $yaml .= self::indentationString($indentLevel, $indentSize) . $key . ':' . PHP_EOL;
                    $yaml .= self::arrayToYaml($value, $indentLevel + 1, $indentSize);
                }
            } else {
                if ($isSequential) {
                    $yaml .= self::indentationString($indentLevel, $indentSize) . '- ' . self::formatValue($value) . PHP_EOL;
                } else {
                    $yaml .= self::indentationString($indentLevel, $indentSize) . $key . ': ' . self::formatValue($value) . PHP_EOL;
                }
            }
            if ($newlineAfterRootElements === true) {
                $yaml .= PHP_EOL;
            }
        }

        return $yaml;
    }

    public static function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            // If the string contains special characters, wrap it in quotes
            if (preg_match('/[\/:{}\[\],&*#?|\-<>=!%@`]/', $value)) {
                return '"' . addslashes($value) . '"';
            }

            return $value;
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        } else {
            return (string)$value;
        }
    }

    public static function getIndentLength(string $line): int
    {
        return strlen($line) - strlen(ltrim($line, ' '));
    }

    public static function findStartingLine(array $lines, int $startLine): ?int
    {
        $indentLength = self::getIndentLength($lines[$startLine]);
        $lineKeys = array_keys($lines);
        $startLineIndexInKeys = array_search($startLine, $lineKeys);
        for ($i = $startLineIndexInKeys - 1; $i >= 0; $i--) {
            $currentLineNumber = $lineKeys[$i];
            $currentLineIndentLength = self::getIndentLength($lines[$currentLineNumber]);
            if (
                $currentLineIndentLength !== $indentLength
                || !str_starts_with(trim($lines[$currentLineNumber]), '#')
            ) {
                return $lineKeys[$i + 1];
            }
        }

        return $startLine;
    }

    public static function findClosingLine(array $lines, int $startLine): int
    {
        $indentLength = self::getIndentLength($lines[$startLine]);
        for ($i = $startLine + 1; $i < count($lines); $i++) {
            if (self::getIndentLength($lines[$i]) <= $indentLength && trim($lines[$i]) !== '') {
                return $i;
            }
        }

        return count($lines);
    }

    public static function cleanObjectFromLines(array $lines, array $clearKeys): array
    {
        $blocksToRemove = [];
        foreach ($clearKeys as $itemKey) {
            foreach ($lines as $lineNumber => $line) {
                if (str_starts_with(trim($line), $itemKey . ':')) {
                    $blocksToRemove[] = $lineNumber;

                    break;
                }
            }
        }

        while ($lineNumber = array_pop($blocksToRemove)) {
            $startLine = $lineNumber;
            $startLineUpwardsSearch = self::findStartingLine($lines, $startLine);
            $endLine = self::findClosingLine($lines, $startLine);
            for ($i = $startLineUpwardsSearch; $i < $endLine; $i++) {
                unset($lines[$i]);
            }
        }

        return $lines;
    }

    public static function insertArrayIntoObject(array $lines, string $insertBlockKey, array $insertArray): array
    {
        $insertObjectOpeningLine = null;
        foreach ($lines as $lineNumber => $line) {
            if (str_starts_with(trim($line), $insertBlockKey . ':')) {
                $insertObjectOpeningLine = $lineNumber;

                break;
            }
        }

        if ($insertObjectOpeningLine === null) {
            throw new RuntimeException("Could not find key '$insertBlockKey' in YAML content.");
        }

        $insertObjectEndLine = self::findClosingLine($lines, $insertObjectOpeningLine);

        $blockLines = array_slice($lines, $insertObjectOpeningLine, $insertObjectEndLine - $insertObjectOpeningLine, true);

        $cleanedBlockLines = self::cleanObjectFromLines($blockLines, array_keys($insertArray));

        $indentLevel = self::getIndentLength($lines[$insertObjectOpeningLine]) / 4 + 1;
        $yamlToInsert = self::arrayToYaml($insertArray, $indentLevel, newlineAfterRootElements: true);

        $yamlToInsertLines = explode(PHP_EOL, $yamlToInsert);
        $combinedBlockLines = array_merge($cleanedBlockLines, $yamlToInsertLines);

        $finalLines = array_merge(
            array_slice($lines, 0, $insertObjectOpeningLine, true),
            $combinedBlockLines,
            array_slice($lines, $insertObjectEndLine, null, true),
        );

        return $finalLines;
    }
}
