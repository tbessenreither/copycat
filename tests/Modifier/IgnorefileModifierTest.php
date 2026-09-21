<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tbessenreither\Copycat\Enum\VerbosityEnum;
use Tbessenreither\Copycat\Modifier\IgnoreFileModifier;
use Tbessenreither\Copycat\Service\ConsoleOutput;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(IgnoreFileModifier::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(VerbosityEnum::class)]
class IgnorefileModifierTest extends TestCase
{
    public function testAddCreatesNamespacedGroupAndKeepsExistingContent(): void
    {
        $result = IgnoreFileModifier::add(
            fileContent: '.git/' . PHP_EOL,
            entries: ['tests/', '.github/'],
            groupName: 'testgroup',
            fileName: '.dockerignore',
        );

        $this->assertStringContainsString(implode(PHP_EOL, [
            '###> testgroup',
            'tests/',
            '.github/',
            '###< testgroup',
        ]), $result['content']);
        $this->assertStringContainsString('.git/', $result['content']);
        $this->assertSame(['tests/', '.github/'], $result['added']);
        $this->assertSame([], $result['skipped']);
    }

    public function testAddSkipsEntriesAlreadyPresentInTheGroup(): void
    {
        $once = IgnoreFileModifier::add(
            fileContent: '',
            entries: ['tests/'],
            groupName: 'testgroup',
            fileName: '.dockerignore',
        );
        $twice = IgnoreFileModifier::add(
            fileContent: $once['content'],
            entries: ['tests/'],
            groupName: 'testgroup',
            fileName: '.dockerignore',
        );

        $this->assertSame(1, substr_count($twice['content'], 'tests/'));
        $this->assertSame([], $twice['added']);
        $this->assertSame(['tests/'], $twice['skipped']);
    }

    public function testAddIsSilentUnderNormalVerbosity(): void
    {
        ConsoleOutput::reset();
        ConsoleOutput::setColorsEnabled(false);
        ConsoleOutput::setVerbosity(VerbosityEnum::NORMAL);
        ConsoleOutput::startCapture();

        IgnoreFileModifier::add(
            fileContent: '',
            entries: ['tests/', '.github/'],
            groupName: 'testgroup',
            fileName: '.dockerignore',
        );

        $captured = ConsoleOutput::stopCapture();
        ConsoleOutput::reset();

        $this->assertSame('', $captured);
    }

    public function testRemoveWithoutGroupReportsTheFileName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no valid group start and end found in .dockerignore for group: testgroup');

        IgnoreFileModifier::remove(
            fileContent: 'tests/',
            groupName: 'testgroup',
            fileName: '.dockerignore',
        );
    }
}
