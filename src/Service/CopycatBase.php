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
        echo '        ' . $method . " Error - " . $e->getMessage() . PHP_EOL;
    }

    protected function getTargetDir(CopyTargetEnum $target): string
    {
        return $this->projectRoot . '/' . $target->value;
    }

}
