<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tbessenreither\Copycat\Modifier\GitignoreModifier;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(GitignoreModifier::class)]
class GitignoreModifierTest extends TestCase
{
    public function testAddCreatesNamespacedGroupAndKeepsExistingContent(): void
    {
        ob_start();
        $modified = GitignoreModifier::add(
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
        $once = GitignoreModifier::add(
            fileContent: '',
            entries: ['tests/'],
            groupName: 'testgroup',
            fileName: '.dockerignore',
        );
        $twice = GitignoreModifier::add(
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

        GitignoreModifier::remove(
            fileContent: 'tests/',
            groupName: 'testgroup',
            fileName: '.dockerignore',
        );
    }
}
