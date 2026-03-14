<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Modifier;

use RuntimeException;

class GitignoreModifier
{
    private const string GROUP_START = '###> ';
    public const string GROUP_END = '###< ';

    /**
     * @param string|string[] $entry
     */
    public static function add(string $fileContent, array|string $entries, string $groupName): string
    {
        $stats = ['added' => 0, 'skipped' => 0];
        if (!is_array($entries)) {
            $entries = [$entries];
        }

        $fileContent = rtrim($fileContent);

        $lines = explode(PHP_EOL, $fileContent);

        ['start' => $groupStartString, 'end' => $groupEndString] = self::getGroupStartAndStopStrings($groupName);

        $groupIndexStart = array_search($groupStartString, $lines, true);
        $groupIndexEnd = array_search($groupEndString, $lines, true);
        if ($groupIndexStart === false) {
            // Group does not exist, add it at the end of the file
            $lines[] = '';
            $lines[] = $groupStartString;
            $lines[] = $groupEndString;
            $groupIndexStart = count($lines) - 2;
            $groupIndexEnd = $groupIndexStart + 1;
        }

        if ($groupIndexEnd === false) {
            throw new RuntimeException('Group start found but group end not found in .gitignore for group: ' . $groupName);
        }

        // cut out the existing group entries
        $linesBeforeGroup = array_slice($lines, 0, $groupIndexStart + 1);
        $groupLines = array_slice($lines, $groupIndexStart + 1, $groupIndexEnd - $groupIndexStart - 1);
        $linesAfterGroup = array_slice($lines, $groupIndexEnd);

        foreach ($entries as $entry) {
            if (!in_array($entry, $groupLines, true)) {
                $groupLines[] = $entry;
                $stats['added']++;
            } else {
                $stats['skipped']++;
            }
        }

        //put the group back into $linesWithoutGroup at the position of $groupIndexStart
        $lines = array_merge(
            $linesBeforeGroup,
            $groupLines,
            $linesAfterGroup,
        );

        // Ensure the file ends with a newline
        $lines[] = '';

        echo "        Added " . $stats['added'] . " entries to .gitignore, skipped " . $stats['skipped'] . " entries that already existed." . PHP_EOL;

        return implode(PHP_EOL, $lines);
    }

    public static function remove(string $fileContent, string $groupName): string
    {
        ['start' => $groupStartString, 'end' => $groupEndString] = self::getGroupStartAndStopStrings($groupName);

        $lines = explode(PHP_EOL, $fileContent);
        $groupIndexStart = array_search($groupStartString, $lines, true);
        $groupIndexEnd = array_search($groupEndString, $lines, true);
        if ($groupIndexStart === false || $groupIndexEnd === false) {
            throw new RuntimeException('no valid group start and end found in .gitignore for group: ' . $groupName);
        }

        // Remove the group lines
        $lines = array_merge(
            array_slice($lines, 0, $groupIndexStart),
            array_slice($lines, $groupIndexEnd + 1)
        );

        return implode(PHP_EOL, $lines);
    }

    /**
     * @return array{end: string, start: string}
     */
    private static function getGroupStartAndStopStrings(string $groupName): array
    {
        return [
            'start' => self::GROUP_START . $groupName,
            'end' => self::GROUP_END . $groupName,
        ];
    }

}
