<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Exception;

use RuntimeException;
use Tbessenreither\Copycat\Enum\KnownSystemsEnum;

class SystemCheckFailedException extends RuntimeException
{
    public function __construct(KnownSystemsEnum $system)
    {
        parent::__construct('The current project does not appear to be a ' . $system->value . ' project. Aborting operation.');
    }

}