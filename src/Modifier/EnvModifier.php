<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Modifier;

use RuntimeException;
use Tbessenreither\Copycat\Dto\EnvVar;
use Tbessenreither\Copycat\Service\ConsoleOutput;

class EnvModifier
{
    private const string GROUP_START = '###> ';
    public const string GROUP_END = '###< ';

    /**
     * Add or update the given entries in the namespaced group inside `$fileContent`.
     *
     * Returns the modified content alongside a per-name breakdown of what happened
     * so the caller (Copycat) can render a grouped, file-scoped summary. The modifier
     * itself no longer emits user-facing output — only DEBUG plumbing lines.
     *
     * @param EnvVar[] $entries
     *
     * @return array{content: string, added: string[], replaced: string[], skipped: string[]}
     */
    public static function add(string $fileContent, array $entries, string $groupName, bool $overwrite = false): array
    {
        $added = [];
        $replaced = [];
        $skipped = [];

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

            // first we clean up any grouping issues by moving any existing entries with the same key into the group, so that we can handle them properly with the overwrite flag
            foreach ($linesBeforeGroup as $lineKey => $line) {
                if (str_starts_with($line, $entrySearchKey)) {
                    ConsoleOutput::debug(sprintf("Moving %s into group", $envVar->getName()), 2);
                    $groupLines[] = $line;
                    unset($linesBeforeGroup[$lineKey]);
                }
            }
            foreach ($linesAfterGroup as $lineKey => $line) {
                if (str_starts_with($line, $entrySearchKey)) {
                    ConsoleOutput::debug(sprintf("Moving %s into group", $envVar->getName()), 2);
                    $groupLines[] = $line;
                    unset($linesAfterGroup[$lineKey]);
                }
            }

            // now that we have cleaned up the grouping we can go over the relevant
            $entryExists = false;
            foreach ($groupLines as $lineKey => $line) {
                if (str_starts_with($line, $entrySearchKey)) {
                    if ($overwrite) {
                        unset($groupLines[$lineKey]);
                        // record as replaced (only once — subsequent duplicate lines
                        // don't count as additional replacements)
                        if (!$entryExists) {
                            $replaced[] = $envVar->getName();
                        }
                        $entryExists = true;
                        // continue scanning to remove all duplicate lines with this key
                    } else {
                        $entryExists = true;
                    }
                }
            }
            if (!$entryExists) {
                $groupLines[] = $envVar->__toString();
                $added[] = $envVar->getName();
            } elseif (!$overwrite) {
                $skipped[] = $envVar->getName();
            } else {
                // overwrite=true and entry existed — write the fresh value
                $groupLines[] = $envVar->__toString();
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

        ConsoleOutput::debug(
            sprintf('Env group "%s": %d added, %d replaced, %d skipped.', $groupName, count($added), count($replaced), count($skipped)),
            2,
        );

        return [
            'content' => implode(PHP_EOL, $lines),
            'added' => $added,
            'replaced' => $replaced,
            'skipped' => $skipped,
        ];
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
