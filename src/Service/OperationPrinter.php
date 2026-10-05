<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

/**
 * Turns coalesced operation groups into CLI lines.
 *
 * Consumes the array returned by {@see OperationRecorder::drain()} and emits
 * one summary block per package to {@see ConsoleOutput}. All layout rules
 * live here — the recorder is data-only, the runner just wires things up.
 *
 * Layout rules (see the README "Verbosity" section for the full spec):
 *
 *   - Env:     file heading + per-name adds/replaces; skips collapse to a
 *              count at NORMAL, expand to `⏭ NAME` lines at VERBOSE.
 *   - Copy:    grouped by target directory; inline when ≤ 5 items and the
 *              rendered line is ≤ 100 chars, otherwise `— N copied` at
 *              NORMAL / per-name list at VERBOSE.
 *   - Ignore:  per-file summary (`— N added`); zero-add cases silent at
 *              NORMAL, surfaced at VERBOSE.
 *   - JSON:    single line per path, silent-on-skip at NORMAL.
 *   - Bundle:  single line per class, silent-on-skip at NORMAL.
 *   - Service: single line per class (addServiceToYaml always mutates).
 *
 * The class is stateless — one instance can be reused across every package
 * in a run.
 */
final class OperationPrinter
{
    /** Threshold for the inline-when-short rule (character budget, indent included). */
    private const int INLINE_MAX_LINE_LENGTH = 100;

    /** Threshold for the inline-when-short rule (max entries). */
    private const int INLINE_MAX_ENTRIES = 5;

    /**
     * Print each group as one or more CLI lines and report whether anything
     * user-visible was emitted.
     *
     * @param array<int, array<string, mixed>> $groups Coalesced groups from {@see OperationRecorder::drain()}.
     * @return bool `true` when at least one group produced a visible line.
     */
    public function emit(array $groups): bool
    {
        $emittedAny = false;
        foreach ($groups as $group) {
            $emittedAny = $this->emitGroup($group) || $emittedAny;
        }

        return $emittedAny;
    }

    /**
     * @param array<string, mixed> $group
     * @return bool `true` when the group produced at least one visible line.
     */
    private function emitGroup(array $group): bool
    {
        return match ($group['type']) {
            'env' => $this->emitEnv($group),
            'copy' => $this->emitCopy($group),
            'ignore' => $this->emitIgnore($group),
            'json' => $this->emitJson($group),
            'bundle' => $this->emitBundle($group),
            'service' => $this->emitService($group),
            default => false,
        };
    }

    // ------------------------------------------------------------------
    // Per-type emit methods
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $group
     */
    private function emitEnv(array $group): bool
    {
        $file = (string) $group['file'];
        /** @var string[] $added */
        $added = $group['added'] ?? [];
        /** @var string[] $replaced */
        $replaced = $group['replaced'] ?? [];
        /** @var string[] $skipped */
        $skipped = $group['skipped'] ?? [];

        if (ConsoleOutput::isVerbose()) {
            $this->emitFileHeading($file);
            foreach ($added as $name) {
                ConsoleOutput::info('+ ' . $name, 2);
            }
            foreach ($replaced as $name) {
                ConsoleOutput::info('↻ ' . $name, 2);
            }
            foreach ($skipped as $name) {
                ConsoleOutput::info('<dim>⏭ ' . $name . '</dim>', 2);
            }

            return true;
        }

        $activeCount = count($added) + count($replaced);
        $skippedCount = count($skipped);

        if ($activeCount === 0) {
            if ($skippedCount === 0) {
                return false;
            }
            ConsoleOutput::info(
                sprintf('<dim>•</dim> %s: %d already set — skipped', $file, $skippedCount),
                1,
            );

            return true;
        }

        // Try inline first.
        $items = [];
        foreach ($added as $name) {
            $items[] = '+ ' . $name;
        }
        foreach ($replaced as $name) {
            $items[] = '↻ ' . $name;
        }
        $itemsStr = implode(', ', $items);
        $skipSuffix = $skippedCount > 0 ? sprintf(' (%d already set)', $skippedCount) : '';
        $inlinePayload = sprintf('%s: %s%s', $file, $itemsStr, $skipSuffix);

        if ($this->fitsInline($activeCount, $inlinePayload)) {
            ConsoleOutput::info('<dim>•</dim> ' . $inlinePayload, 1);

            return true;
        }

        // Multiline fallback at NORMAL.
        $this->emitFileHeading($file);
        foreach ($added as $name) {
            ConsoleOutput::info('+ ' . $name, 2);
        }
        foreach ($replaced as $name) {
            ConsoleOutput::info('↻ ' . $name, 2);
        }
        if ($skippedCount > 0) {
            ConsoleOutput::info(
                sprintf('<dim>(%d already set — skipped)</dim>', $skippedCount),
                2,
            );
        }

        return true;
    }

    /**
     * @param array<string, mixed> $group
     */
    private function emitCopy(array $group): bool
    {
        $targetDir = rtrim((string) $group['targetDir'], '/') . '/';
        /** @var string[] $added */
        $added = $group['added'] ?? [];
        /** @var string[] $skipped */
        $skipped = $group['skipped'] ?? [];
        $addedCount = count($added);
        $skippedCount = count($skipped);

        if (ConsoleOutput::isVerbose()) {
            if ($addedCount === 0 && $skippedCount === 0) {
                return false;
            }
            $this->emitFileHeading($targetDir);
            foreach ($added as $name) {
                ConsoleOutput::info('+ ' . $name, 2);
            }
            if ($skippedCount > 0) {
                ConsoleOutput::info(
                    sprintf(
                        '<dim>(%d already %s — skipped)</dim>',
                        $skippedCount,
                        $skippedCount === 1 ? 'exists' : 'exist',
                    ),
                    2,
                );
            }

            return true;
        }

        // NORMAL: silent when nothing was actually copied.
        if ($addedCount === 0) {
            return false;
        }

        $inlinePayload = sprintf('%s: %s', $targetDir, implode(', ', $added));
        if ($this->fitsInline($addedCount, $inlinePayload)) {
            ConsoleOutput::info('<dim>•</dim> ' . $inlinePayload, 1);

            return true;
        }

        ConsoleOutput::info(
            sprintf('<dim>•</dim> %s — %d copied', $targetDir, $addedCount),
            1,
        );

        return true;
    }

    /**
     * @param array<string, mixed> $group
     */
    private function emitIgnore(array $group): bool
    {
        $file = (string) $group['file'];
        /** @var string[] $added */
        $added = $group['added'] ?? [];
        /** @var string[] $skipped */
        $skipped = $group['skipped'] ?? [];
        $addedCount = count($added);
        $skippedCount = count($skipped);

        if (ConsoleOutput::isVerbose()) {
            if ($addedCount === 0 && $skippedCount === 0) {
                return false;
            }
            if ($addedCount === 0) {
                ConsoleOutput::info(
                    sprintf(
                        '<dim>•</dim> %s — 0 added, %d already present',
                        $file,
                        $skippedCount,
                    ),
                    1,
                );

                return true;
            }
            $this->emitFileHeading($file);
            foreach ($added as $entry) {
                ConsoleOutput::info('+ ' . $entry, 2);
            }
            if ($skippedCount > 0) {
                ConsoleOutput::info(
                    sprintf('<dim>(%d already present)</dim>', $skippedCount),
                    2,
                );
            }

            return true;
        }

        // NORMAL: silent when no adds.
        if ($addedCount === 0) {
            return false;
        }
        ConsoleOutput::info(
            sprintf('<dim>•</dim> %s — %d added', $file, $addedCount),
            1,
        );

        return true;
    }

    /**
     * @param array<string, mixed> $group
     */
    private function emitJson(array $group): bool
    {
        $file = (string) $group['file'];
        $path = (string) $group['path'];
        $changed = (bool) ($group['changed'] ?? false);

        if (!$changed) {
            if (ConsoleOutput::isVerbose()) {
                $this->emitFileHeading($file);
                ConsoleOutput::info(sprintf('<dim>⏭ %s (already present)</dim>', $path), 2);

                return true;
            }

            return false;
        }

        ConsoleOutput::info(
            sprintf('<dim>•</dim> %s: added %s', $file, $path),
            1,
        );

        return true;
    }

    /**
     * @param array<string, mixed> $group
     */
    private function emitBundle(array $group): bool
    {
        $file = (string) $group['file'];
        $identifier = (string) $group['identifier'];
        $changed = (bool) ($group['changed'] ?? false);

        if (!$changed) {
            if (ConsoleOutput::isVerbose()) {
                $this->emitFileHeading($file);
                ConsoleOutput::info(sprintf('<dim>⏭ %s (already registered)</dim>', $identifier), 2);

                return true;
            }

            return false;
        }

        ConsoleOutput::info(
            sprintf('<dim>•</dim> %s: + %s', $file, $identifier),
            1,
        );

        return true;
    }

    /**
     * @param array<string, mixed> $group
     */
    private function emitService(array $group): bool
    {
        $file = (string) $group['file'];
        $identifier = (string) $group['identifier'];

        // addServiceToYaml always mutates the YAML — no skip branch to render.
        ConsoleOutput::info(
            sprintf('<dim>•</dim> %s: + %s', $file, $identifier),
            1,
        );

        return true;
    }

    // ------------------------------------------------------------------
    // Layout helpers
    // ------------------------------------------------------------------

    private function emitFileHeading(string $file): void
    {
        ConsoleOutput::info('<dim>•</dim> ' . $file, 1);
    }

    /**
     * Decide whether the inline form fits according to the shared rule:
     * `≤ INLINE_MAX_ENTRIES entries AND rendered line ≤ INLINE_MAX_LINE_LENGTH chars`.
     * The payload should not include the leading `• ` bullet or the indent —
     * those are added by the caller and factored into the budget here.
     */
    private function fitsInline(int $entryCount, string $payload): bool
    {
        if ($entryCount > self::INLINE_MAX_ENTRIES) {
            return false;
        }
        // 4 spaces of indent (level 1) + "• " visible prefix.
        $prefixLen = 4 + 2;

        return $prefixLen + mb_strlen($payload) <= self::INLINE_MAX_LINE_LENGTH;
    }
}
