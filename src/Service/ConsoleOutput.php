<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

use Tbessenreither\Copycat\Enum\VerbosityEnum;

/**
 * Central sink for all CLI output produced by Copycat.
 *
 * All output that used to be emitted via raw `echo` should go through this
 * class so it can be filtered by verbosity, colored consistently, and captured
 * for tests. The class is fully static because Copycat's execution model is a
 * single Composer hook per process; there is no need to instantiate it.
 *
 * Configuration precedence (highest to lowest):
 *   1. Explicit call to {@see self::setVerbosity()}.
 *   2. Environment variable `COPYCAT_VERBOSITY` (name or numeric level).
 *   3. Default: {@see VerbosityEnum::NORMAL}.
 *
 * Inline markup — supported in every method that takes a message string:
 *   <b>text</b>     bold. Renders in every modern terminal.
 *   <dim>text</dim>  reduced intensity. Intended for de-emphasising filler
 *                     prefixes (e.g. `<dim>+ created</dim> path/to/file`) so
 *                     the identifier that follows reads at normal weight.
 *   When colors are disabled the tags are stripped so the inner text stays
 *   intact.
 *
 * Symbol vocabulary — used by callers to signal intent at a glance. All
 * symbols are followed by exactly one space before the payload text.
 *
 *   ✖  fatal error. Auto-prefixed by {@see self::error()}, do not add manually.
 *   ⚠  non-fatal problem. Auto-prefixed by {@see self::warning()}.
 *   •  "about to do X" — preflight / announce.
 *   ✓  "done" — operation completed successfully.
 *   +  "added a new entry" — e.g. gitignore/env line inserted.
 *   ↻  "replaced existing entry" — value overwritten under overwrite=true.
 *   ⏭  "skipped" — entry already present, or filtered out.
 *   📦  package boundary — used by {@see self::heading()} on the per-package
 *       banner emitted from {@see \Tbessenreither\Copycat\Runner}.
 *
 * Indentation is exclusively controlled by the `$indent` parameter (multiplied
 * by 4 spaces). Do not hard-code leading whitespace in message strings.
 */
final class ConsoleOutput
{
    public const ANSI_RESET = "\033[0m";
    public const ANSI_RED = "\033[31m";
    public const ANSI_GREEN = "\033[32m";
    public const ANSI_YELLOW = "\033[33m";
    public const ANSI_BLUE = "\033[34m";
    public const ANSI_MAGENTA = "\033[35m";
    public const ANSI_CYAN = "\033[36m";
    public const ANSI_BOLD = "\033[1m";
    public const ANSI_BOLD_OFF = "\033[22m";
    public const ANSI_DIM = "\033[2m";
    public const ANSI_DIM_OFF = "\033[22m";

    private const ENV_VAR = 'COPYCAT_VERBOSITY';

    private static ?VerbosityEnum $verbosity = null;

    /**
     * When true, output is appended to {@see self::$buffer} instead of being
     * written to stdout. Used by tests via {@see self::startCapture()}.
     */
    private static bool $capturing = false;
    private static string $buffer = '';

    /**
     * When true, ANSI escape sequences are stripped from all output. Auto-set
     * when stdout is not a TTY (e.g. piped into a file), and can be forced via
     * {@see self::setColorsEnabled()}.
     */
    private static ?bool $colorsEnabled = null;

    /**
     * Prevent instantiation — this is a static-only service.
     */
    private function __construct()
    {
    }

    // ------------------------------------------------------------------
    // Configuration
    // ------------------------------------------------------------------

    public static function setVerbosity(VerbosityEnum $verbosity): void
    {
        self::$verbosity = $verbosity;
    }

    public static function getVerbosity(): VerbosityEnum
    {
        if (self::$verbosity === null) {
            self::$verbosity = self::resolveDefaultVerbosity();
        }

        return self::$verbosity;
    }

    public static function setColorsEnabled(bool $enabled): void
    {
        self::$colorsEnabled = $enabled;
    }

    /**
     * Reset all configuration and captured state. Primarily useful for tests.
     */
    public static function reset(): void
    {
        self::$verbosity = null;
        self::$colorsEnabled = null;
        self::$capturing = false;
        self::$buffer = '';
    }

    // ------------------------------------------------------------------
    // Leveled output
    // ------------------------------------------------------------------

    /**
     * Always emitted, even at SILENT verbosity. Rendered in red and prefixed
     * with `✖ ` so it stands out even in monochrome output.
     */
    public static function error(string $message, int $indent = 0): void
    {
        self::emitLine(self::color('✖ ' . $message, self::ANSI_RED), $indent, VerbosityEnum::SILENT);
    }

    /**
     * Non-fatal problem the user should still see. Emitted at NORMAL+.
     * Rendered in yellow and prefixed with `⚠ ` for the same reason as
     * {@see self::error()}.
     */
    public static function warning(string $message, int $indent = 0): void
    {
        self::emitLine(self::color('⚠ ' . $message, self::ANSI_YELLOW), $indent, VerbosityEnum::NORMAL);
    }

    /**
     * Standard progress/action line. Emitted at NORMAL+.
     */
    public static function info(string $message, int $indent = 0): void
    {
        self::emitLine($message, $indent, VerbosityEnum::NORMAL);
    }

    /**
     * Positive/completion message. Emitted at NORMAL+. Rendered in green.
     */
    public static function success(string $message, int $indent = 0): void
    {
        self::emitLine(self::color($message, self::ANSI_GREEN), $indent, VerbosityEnum::NORMAL);
    }

    /**
     * Section header. Emitted at NORMAL+. Rendered in blue.
     */
    public static function heading(string $message, int $indent = 0): void
    {
        self::emitLine(self::color(PHP_EOL . $message, self::ANSI_BLUE), $indent, VerbosityEnum::NORMAL);
    }

    /**
     * Detail-level output that is hidden by default. Emitted at VERBOSE+.
     * Use this for "Loading file: …", "Storing modifications for: …",
     * "Writing file to disk: …" and similar chatter.
     */
    public static function verbose(string $message, int $indent = 0): void
    {
        self::emitLine($message, $indent, VerbosityEnum::VERBOSE);
    }

    /**
     * Deep trace output. Only emitted at DEBUG.
     */
    public static function debug(string $message, int $indent = 0): void
    {
        self::emitLine(self::color('[debug] ' . $message, self::ANSI_MAGENTA), $indent, VerbosityEnum::DEBUG);
    }

    // ------------------------------------------------------------------
    // Raw output
    // ------------------------------------------------------------------

    /**
     * Write raw content without appending a newline. Respects the given
     * required level (defaults to NORMAL). ANSI escape sequences in the input
     * are stripped when colors are disabled.
     */
    public static function write(string $content, VerbosityEnum $requiredLevel = VerbosityEnum::NORMAL): void
    {
        if (!self::shouldOutput($requiredLevel)) {
            return;
        }

        self::emitRaw($content);
    }

    /**
     * Same as {@see self::write()} but appends `PHP_EOL`.
     */
    public static function writeln(string $content = '', VerbosityEnum $requiredLevel = VerbosityEnum::NORMAL): void
    {
        self::write($content . PHP_EOL, $requiredLevel);
    }

    /**
     * Emit N blank lines, subject to the given required level.
     */
    public static function newline(int $count = 1, VerbosityEnum $requiredLevel = VerbosityEnum::NORMAL): void
    {
        if ($count < 1 || !self::shouldOutput($requiredLevel)) {
            return;
        }

        self::emitRaw(str_repeat(PHP_EOL, $count));
    }

    // ------------------------------------------------------------------
    // Level check (exposed for callers that build expensive messages)
    // ------------------------------------------------------------------

    public static function isVerbose(): bool
    {
        return self::shouldOutput(VerbosityEnum::VERBOSE);
    }

    public static function isDebug(): bool
    {
        return self::shouldOutput(VerbosityEnum::DEBUG);
    }

    public static function shouldOutput(VerbosityEnum $level): bool
    {
        return self::getVerbosity()->value >= $level->value;
    }

    // ------------------------------------------------------------------
    // Test hooks
    // ------------------------------------------------------------------

    /**
     * Start buffering all output instead of writing it to stdout. Any prior
     * captured content is discarded.
     */
    public static function startCapture(): void
    {
        self::$capturing = true;
        self::$buffer = '';
    }

    /**
     * Stop buffering and return everything captured since the last
     * {@see self::startCapture()} call.
     */
    public static function stopCapture(): string
    {
        $buffer = self::$buffer;
        self::$capturing = false;
        self::$buffer = '';

        return $buffer;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private static function emitLine(string $message, int $indent, VerbosityEnum $requiredLevel): void
    {
        if (!self::shouldOutput($requiredLevel)) {
            return;
        }

        $prefix = $indent > 0 ? str_repeat(' ', $indent * 4) : '';
        self::emitRaw($prefix . $message . PHP_EOL);
    }

    private static function emitRaw(string $content): void
    {
        $content = self::renderInlineMarkup($content, self::colorsEnabled());

        if (!self::colorsEnabled()) {
            $content = self::stripAnsi($content);
        }

        if (self::$capturing) {
            self::$buffer .= $content;

            return;
        }

        echo $content;
    }

    /**
     * Translate the small inline markup vocabulary supported by every leveled
     * output method into ANSI escape sequences (or strip it, when colors are
     * disabled). Currently supported:
     *
     *   <b>text</b>     — bold. Uses `\033[22m` (bold off) rather than `\033[0m`
     *                     (full reset), so surrounding color from `error()`,
     *                     `warning()`, etc. survives past the closing tag.
     *   <dim>text</dim> — reduced intensity (SGR 2). Same close-sequence as
     *                     `<b>` (`\033[22m` clears both bold and dim), so any
     *                     surrounding color survives. Intended for de-emphasising
     *                     filler prefixes like `+ created` while keeping the
     *                     following identifier at normal weight.
     *
     * Tag matching is case-insensitive. Nesting is not supported — the first
     * closing tag turns bold/dim off, regardless of nesting depth, and mixing
     * `<b>` inside `<dim>` (or vice versa) will produce surprising results
     * because both share the SGR 22 reset code.
     */
    private static function renderInlineMarkup(string $content, bool $colorsEnabled): string
    {
        if ($colorsEnabled) {
            $content = preg_replace('#<b>#i', self::ANSI_BOLD, $content) ?? $content;
            $content = preg_replace('#</b>#i', self::ANSI_BOLD_OFF, $content) ?? $content;
            $content = preg_replace('#<dim>#i', self::ANSI_DIM, $content) ?? $content;
            $content = preg_replace('#</dim>#i', self::ANSI_DIM_OFF, $content) ?? $content;

            return $content;
        }

        return preg_replace('#</?(?:b|dim)>#i', '', $content) ?? $content;
    }

    private static function color(string $message, string $ansi): string
    {
        return $ansi . $message . self::ANSI_RESET;
    }

    private static function stripAnsi(string $content): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $content) ?? $content;
    }

    private static function colorsEnabled(): bool
    {
        if (self::$colorsEnabled !== null) {
            return self::$colorsEnabled;
        }

        // Disable colors when NO_COLOR is set (see https://no-color.org)
        // or when stdout is redirected to something that isn't a terminal.
        if (getenv('NO_COLOR') !== false) {
            return self::$colorsEnabled = false;
        }

        $isTty = function_exists('stream_isatty')
            && defined('STDOUT')
            && @stream_isatty(STDOUT);

        return self::$colorsEnabled = (bool) $isTty;
    }

    private static function resolveDefaultVerbosity(): VerbosityEnum
    {
        $envValue = getenv(self::ENV_VAR);
        if ($envValue !== false) {
            $parsed = VerbosityEnum::fromStringOrNull($envValue);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return VerbosityEnum::NORMAL;
    }
}
