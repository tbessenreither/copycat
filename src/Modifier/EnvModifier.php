<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Modifier;

use RuntimeException;
use Tbessenreither\Copycat\Dto\EnvVar;

class EnvModifier
{
    private const string GROUP_START = '###> ';
    public const string GROUP_END = '###< ';

    /**
     * @param EnvVar[] $entries
     */
    public static function add(string $fileContent, array $entries, string $groupName, bool $overwrite = false): string
    {
        $stats = ['added' => 0, 'skipped' => 0];

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

        foreach ($entries as $envVar) {
            $entrySearchKey = $envVar->getName() . '=';

            // first we cleanup anny grouping issues by moving any existing entries with the same key into the group, so that we can handle them properly with the overwrite flag
            foreach ($linesBeforeGroup as $lineKey => $line) {
                if (str_starts_with($line, $entrySearchKey)) {
                    echo "        Entry with key " . $envVar->getName() . " already exists, moving to group." . PHP_EOL;
                    $groupLines[] = $line;
                    unset($linesBeforeGroup[$lineKey]);
                }
            }
            foreach ($linesAfterGroup as $lineKey => $line) {
                if (str_starts_with($line, $entrySearchKey)) {
                    echo "        Entry with key " . $envVar->getName() . " already exists, moving to group." . PHP_EOL;
                    $groupLines[] = $line;
                    unset($linesAfterGroup[$lineKey]);
                }
            }

            // now that we have cleaned up the grouping we can go over the relevant
            $entryExists = false;
            foreach ($groupLines as $lineKey => $line) {
                if (str_starts_with($line, $entrySearchKey)) {
                    if ($overwrite) {
                        echo "        Entry with key " . $envVar->getName() . " already exists in group, overwriting." . PHP_EOL;
                        unset($groupLines[$lineKey]);
                    } else {
                        echo "        Entry with key " . $envVar->getName() . " already exists in group, skipping." . PHP_EOL;
                        $entryExists = true;
                    }
                }
            }
            if (!$entryExists) {
                $groupLines[] = $envVar->__toString();
                $stats['added']++;
            } else {
                $stats['skipped']++;
            }
        }

        $groupLines = array_multisort(
            $groupLines,
            SORT_STRING | SORT_FLAG_CASE,
        ) ? $groupLines : [];

        //put the group back into $linesWithoutGroup at the position of $groupIndexStart
        $lines = array_merge(
            $linesBeforeGroup,
            $groupLines,
            $linesAfterGroup,
        );

        // Ensure the file ends with a newline
        $lines[] = '';

        echo "        Added " . $stats['added'] . " entries and skipped " . $stats['skipped'] . "." . PHP_EOL;

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<string, string> $entries
     */

    public static function remove(string $fileContent, string $groupName): string
    {
        ['start' => $groupStartString, 'end' => $groupEndString] = self::getGroupStartAndStopStrings($groupName);

        $lines = explode(PHP_EOL, $fileContent);
        $groupIndexStart = array_search($groupStartString, $lines, true);
        $groupIndexEnd = array_search($groupEndString, $lines, true);
        if ($groupIndexStart === false || $groupIndexEnd === false) {
            throw new RuntimeException('no valid group start and end found in env for group: ' . $groupName);
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
