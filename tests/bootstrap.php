<?php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}
