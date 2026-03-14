<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function loadTestFile(string $filename): string
    {
        return file_get_contents(__DIR__ . '/TestFiles/' . $filename);
    }

}
