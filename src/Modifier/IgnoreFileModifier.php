<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Modifier;

use RuntimeException;
use Tbessenreither\Copycat\Service\ConsoleOutput;

class IgnoreFileModifier
{
    private const string GROUP_START = '###> ';
    public const string GROUP_END = '###< ';

    /**
     * Add the given entries to the namespaced group inside `$fileContent`.
     *
     * Returns the modified content alongside a per-entry breakdown so the caller
     * can render a grouped, file-scoped summary. The modifier itself no longer
     * emits user-facing output — only DEBUG plumbing lines.
     *
     * @param string|string[] $entries
     *
     * @return array{content: string, added: string[], skipped: string[]}
     */
    public static function add(string $fileContent, array|string $entries, string $groupName, string $fileName = '.gitignore'): array
    {
        $added = [];
        $skipped = [];
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
            throw new RuntimeException('Group start found but group end not found in ' . $fileName . ' for group: ' . $groupName);
        }

        // cut out the existing group entries
        $linesBeforeGroup = array_slice($lines, 0, $groupIndexStart + 1);
        $groupLines = array_slice($lines, $groupIndexStart + 1, $groupIndexEnd - $groupIndexStart - 1);
        $linesAfterGroup = array_slice($lines, $groupIndexEnd);

        foreach ($entries as $entry) {
            if (!in_array($entry, $groupLines, true)) {
                $groupLines[] = $entry;
                $added[] = $entry;
            } else {
                $skipped[] = $entry;
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

        ConsoleOutput::debug(
            sprintf('Ignore group "%s" in %s: %d added, %d skipped.', $groupName, $fileName, count($added), count($skipped)),
            2,
        );

        return [
            'content' => implode(PHP_EOL, $lines),
            'added' => $added,
            'skipped' => $skipped,
        ];
    }

    public static function remove(string $fileContent, string $groupName, string $fileName = '.gitignore'): string
    {
        ['start' => $groupStartString, 'end' => $groupEndString] = self::getGroupStartAndStopStrings($groupName);

        $lines = explode(PHP_EOL, $fileContent);
        $groupIndexStart = array_search($groupStartString, $lines, true);
        $groupIndexEnd = array_search($groupEndString, $lines, true);
        if ($groupIndexStart === false || $groupIndexEnd === false) {
            throw new RuntimeException('no valid group start and end found in ' . $fileName . ' for group: ' . $groupName);
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
