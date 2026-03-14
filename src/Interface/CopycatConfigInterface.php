<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Interface;

interface CopycatConfigInterface
{
    public static function run(CopycatInterface $copycat): void;

}
