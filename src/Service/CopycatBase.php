<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

use Tbessenreither\Copycat\Dto\PackageInfo;
use Tbessenreither\Copycat\Enum\CopyTargetEnum;
use Throwable;

abstract class CopycatBase
{
    public function __construct(
        protected PackageInfo $packageInfo,
        protected ?string $projectRoot = null,
    ) {
        if ($this->projectRoot === null) {
            $this->projectRoot = FileResolver::getProjectRootDir();
        }
    }

    protected function logError(string $method, Throwable $e): void
    {
        $method = self::shortMethodName($method);

        if (ConsoleOutput::isDebug()) {
            ConsoleOutput::debug(sprintf("Method %s Error! - %s", $method, $e->getMessage()), 2);
        } else {
            ConsoleOutput::warning(sprintf('%s: %s', $method, $e->getMessage()), 2);
        }
    }

    /**
     * Reduces a method identifier to its bare method name.
     *
     * Callers pass either a hand-written short name (`'copyDirectory'`) or
     * `__METHOD__` (`'…\\Copycat::envAdd'`); we always want the trailing
     * `envAdd` for user-facing warnings.
     */
    private static function shortMethodName(string $method): string
    {
        $sep = strrpos($method, '::');

        return $sep === false ? $method : substr($method, $sep + 2);
    }

    protected function getTargetDir(CopyTargetEnum $target): string
    {
        return $this->projectRoot . '/' . $target->value;
    }

}
