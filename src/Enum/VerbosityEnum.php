<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Enum;

/**
 * Verbosity levels for {@see \Tbessenreither\Copycat\Service\ConsoleOutput}.
 *
 * Higher values enable more output. A message is emitted when the currently
 * configured verbosity is greater than or equal to the message's level.
 *
 * Visibility matrix:
 *
 *  Level    | error | warning | info | verbose | debug
 *  SILENT   |   ✓   |    ✗    |  ✗   |    ✗    |  ✗
 *  NORMAL   |   ✓   |    ✓    |  ✓   |    ✗    |  ✗
 *  VERBOSE  |   ✓   |    ✓    |  ✓   |    ✓    |  ✗
 *  DEBUG    |   ✓   |    ✓    |  ✓   |    ✓    |  ✓
 */
enum VerbosityEnum: int
{
    case SILENT = 0;
    case NORMAL = 1;
    case VERBOSE = 2;
    case DEBUG = 3;

    /**
     * Parse a verbosity value from a case-insensitive name (e.g. "verbose")
     * or its numeric level ("0".."3"). Returns null when the input is unknown.
     */
    public static function fromStringOrNull(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));
        if ($normalized === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if ($case->name === $normalized) {
                return $case;
            }
        }

        if (ctype_digit($normalized)) {
            return self::tryFrom((int) $normalized);
        }

        return null;
    }
}
