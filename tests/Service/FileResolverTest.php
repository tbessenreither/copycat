<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Service;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tbessenreither\Copycat\Config\FileFilter;
use Tbessenreither\Copycat\Dto\FilterItem;
use Tbessenreither\Copycat\Enum\FilterTypeEnum;
use Tbessenreither\Copycat\Service\FileResolver;
use Tbessenreither\Copycat\Service\FilterService;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(FileResolver::class)]
#[UsesClass(FilterItem::class)]
#[UsesClass(FilterTypeEnum::class)]
#[UsesClass(FilterService::class)]
#[UsesClass(FileFilter::class)]
class FileResolverTest extends TestCase
{
    public function testLoadNonExistingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found: non-existing-file.txt');

        try {
            ob_start();
            FileResolver::loadFile(
                file: 'non-existing-file.txt',
            );
        } catch (InvalidArgumentException $e) {
            throw $e;
        } finally {
            ob_end_clean();
        }
    }

    public function testLoadBlacklistedFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File is blacklisted and cannot be loaded');

        $testBlacklist = new FilterItem(
            key: 'root',
            type: FilterTypeEnum::NONE,
            children: [
                new FilterItem(
                    key: 'README.md',
                    type: FilterTypeEnum::BLACKLIST,
                ),
            ]
        );

        ob_start();
        try {
            FileResolver::loadFile(
                file: 'README.md',
                useFilterItem: $testBlacklist,
            );
        } catch (InvalidArgumentException $e) {
            throw $e;
        } finally {
            ob_end_clean();
        }
    }

    public function testLoadWhitelistedFile(): void
    {
        $testBlacklist = new FilterItem(
            key: 'root',
            type: FilterTypeEnum::NONE,
            children: [
                new FilterItem(
                    key: 'README.md',
                    type: FilterTypeEnum::WHITELIST,
                ),
            ]
        );

        ob_start();
        $resolvedFile = FileResolver::loadFile(
            file: 'README.md',
            useFilterItem: $testBlacklist,
        );
        ob_end_clean();

        $this->assertNotEmpty($resolvedFile);
    }
}
