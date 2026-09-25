<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tbessenreither\Copycat\Modifier\IgnoreFileModifier;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(IgnoreFileModifier::class)]
class IgnorefileModifierTest extends TestCase
{
    public function testAddCreatesNamespacedGroupAndKeepsExistingContent(): void
    {
        ob_start();
        $modified = IgnoreFileModifier::add(
            fileContent: '.git/' . PHP_EOL,
            entries: ['tests/', '.github/'],
            groupName: 'testgroup',
            fileName: '.dockerignore',
        );
        ob_end_clean();

        $this->assertStringContainsString(implode(PHP_EOL, [
            '###> testgroup',
            'tests/',
            '.github/',
            '###< testgroup',
        ]), $modified);
        $this->assertStringContainsString('.git/', $modified);
    }

    public function testAddSkipsEntriesAlreadyPresentInTheGroup(): void
    {
        ob_start();
        $once = IgnoreFileModifier::add(
            fileContent: '',
            entries: ['tests/'],
            groupName: 'testgroup',
            fileName: '.dockerignore',
        );
        $twice = IgnoreFileModifier::add(
            fileContent: $once,
            entries: ['tests/'],
            groupName: 'testgroup',
            fileName: '.dockerignore',
        );
        ob_end_clean();

        $this->assertSame(1, substr_count($twice, 'tests/'));
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

    public function testHasGroupReturnsFalseForEmptyContent(): void
    {
        $this->assertFalse(IgnoreFileModifier::hasGroup('', 'testgroup'));
    }

    public function testHasGroupReturnsFalseWhenMarkersAreAbsent(): void
    {
        $this->assertFalse(IgnoreFileModifier::hasGroup(
            fileContent: implode(PHP_EOL, ['.git/', 'vendor/']),
            groupName: 'testgroup',
        ));
    }

    public function testHasGroupReturnsFalseWhenOnlyStartMarkerIsPresent(): void
    {
        // Truncated / half-written block should not count as present — remove() would fail on it.
        $this->assertFalse(IgnoreFileModifier::hasGroup(
            fileContent: implode(PHP_EOL, ['###> testgroup', 'entry']),
            groupName: 'testgroup',
        ));
    }

    public function testHasGroupReturnsTrueForAWellFormedGroup(): void
    {
        ob_start();
        $content = IgnoreFileModifier::add(
            fileContent: '',
            entries: ['tests/'],
            groupName: 'testgroup',
            fileName: '.dockerignore',
        );
        ob_end_clean();

        $this->assertTrue(IgnoreFileModifier::hasGroup($content, 'testgroup'));
    }

    public function testHasGroupIsGroupNameSpecific(): void
    {
        ob_start();
        $content = IgnoreFileModifier::add(
            fileContent: '',
            entries: ['tests/'],
            groupName: 'testgroup',
            fileName: '.dockerignore',
        );
        ob_end_clean();

        $this->assertFalse(IgnoreFileModifier::hasGroup($content, 'othergroup'));
    }

    public function testAddThenRemoveRestoresFileToPreGroupState(): void
    {
        $baseline = implode(PHP_EOL, ['.git/', 'vendor/']) . PHP_EOL;

        ob_start();
        $withGroup = IgnoreFileModifier::add(
            fileContent: $baseline,
            entries: ['tests/', '.github/'],
            groupName: 'testgroup',
            fileName: '.gitignore',
        );
        $withoutGroup = IgnoreFileModifier::remove(
            fileContent: $withGroup,
            groupName: 'testgroup',
            fileName: '.gitignore',
        );
        ob_end_clean();

        $this->assertFalse(IgnoreFileModifier::hasGroup($withoutGroup, 'testgroup'));
        $this->assertStringContainsString('.git/', $withoutGroup);
        $this->assertStringContainsString('vendor/', $withoutGroup);
    }
}
