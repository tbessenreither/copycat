<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use Tbessenreither\Copycat\Service\OperationRecorder;
use Tbessenreither\Copycat\Tests\TestCase;

/**
 * Unit tests for the pure-data side of the recorder → printer pipeline.
 *
 * These focus on the coalescence rules and the shapes each `record*` method
 * produces. No console output, no verbosity — just `record → drain` semantics.
 */
#[CoversClass(OperationRecorder::class)]
class OperationRecorderTest extends TestCase
{
    public function testEmptyRecorderDrainsToEmptyArray(): void
    {
        $recorder = new OperationRecorder();

        $this->assertSame([], $recorder->drain());
    }

    public function testDrainResetsInternalStateSoSecondDrainIsEmpty(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordEnv('.env.example', ['FOO'], [], []);

        $recorder->drain();
        $this->assertSame([], $recorder->drain());
    }

    public function testRecordEnvProducesExpectedShape(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordEnv('.env.example', ['FOO'], ['BAR'], ['SKIP']);

        $groups = $recorder->drain();

        $this->assertCount(1, $groups);
        $this->assertSame([
            'type' => 'env',
            'file' => '.env.example',
            'added' => ['FOO'],
            'replaced' => ['BAR'],
            'skipped' => ['SKIP'],
        ], $groups[0]);
    }

    public function testRecordCopyPlacesFilenameInAddedOnSuccess(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordCopy('.kiro/settings', 'cli.json', true);

        $this->assertSame([[
            'type' => 'copy',
            'targetDir' => '.kiro/settings',
            'added' => ['cli.json'],
            'skipped' => [],
        ]], $recorder->drain());
    }

    public function testRecordCopyPlacesFilenameInSkippedOnFailure(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordCopy('.git/hooks', 'pre-commit', false);

        $this->assertSame([[
            'type' => 'copy',
            'targetDir' => '.git/hooks',
            'added' => [],
            'skipped' => ['pre-commit'],
        ]], $recorder->drain());
    }

    public function testRecordIgnoreProducesExpectedShape(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordIgnore('.gitignore', ['foo/'], ['bar/']);

        $this->assertSame([[
            'type' => 'ignore',
            'file' => '.gitignore',
            'added' => ['foo/'],
            'skipped' => ['bar/'],
        ]], $recorder->drain());
    }

    public function testRecordJsonProducesExpectedShape(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordJson('composer.json', 'extra.pkg', true);

        $this->assertSame([[
            'type' => 'json',
            'file' => 'composer.json',
            'path' => 'extra.pkg',
            'changed' => true,
        ]], $recorder->drain());
    }

    public function testRecordBundleProducesExpectedShape(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordBundle('config/bundles.php', 'My\\Bundle', true);

        $this->assertSame([[
            'type' => 'bundle',
            'file' => 'config/bundles.php',
            'identifier' => 'My\\Bundle',
            'changed' => true,
        ]], $recorder->drain());
    }

    public function testRecordServiceProducesExpectedShape(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordService('config/services.yaml', 'My\\Service', true);

        $this->assertSame([[
            'type' => 'service',
            'file' => 'config/services.yaml',
            'identifier' => 'My\\Service',
            'changed' => true,
        ]], $recorder->drain());
    }

    public function testConsecutiveEnvRecordsForSameFileCoalesceIntoOneGroup(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordEnv('.env.example', ['FOO'], [], []);
        $recorder->recordEnv('.env.example', ['BAR'], ['STAGE'], ['SKIP']);

        $groups = $recorder->drain();

        $this->assertCount(1, $groups);
        $this->assertSame([
            'type' => 'env',
            'file' => '.env.example',
            'added' => ['FOO', 'BAR'],
            'replaced' => ['STAGE'],
            'skipped' => ['SKIP'],
        ], $groups[0]);
    }

    public function testEnvRecordsForDifferentFilesStayAsSeparateGroups(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordEnv('.env.example', ['FOO'], [], []);
        $recorder->recordEnv('.env.local', ['BAR'], [], []);

        $groups = $recorder->drain();

        $this->assertCount(2, $groups);
        $this->assertSame('.env.example', $groups[0]['file']);
        $this->assertSame('.env.local', $groups[1]['file']);
    }

    public function testConsecutiveCopyRecordsForSameTargetDirMerge(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordCopy('.ddev/commands/web', 'a.sh', true);
        $recorder->recordCopy('.ddev/commands/web', 'b.sh', true);
        $recorder->recordCopy('.ddev/commands/web', 'c.sh', false);

        $groups = $recorder->drain();

        $this->assertCount(1, $groups);
        $this->assertSame([
            'type' => 'copy',
            'targetDir' => '.ddev/commands/web',
            'added' => ['a.sh', 'b.sh'],
            'skipped' => ['c.sh'],
        ], $groups[0]);
    }

    public function testJsonRecordsWithSameFileAndPathCoalesceAndOrChangedFlags(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordJson('composer.json', 'extra.pkg', false);
        $recorder->recordJson('composer.json', 'extra.pkg', true);

        $groups = $recorder->drain();

        $this->assertCount(1, $groups);
        $this->assertTrue($groups[0]['changed']);
    }

    public function testJsonRecordsWithSameFileButDifferentPathStayAsSeparateGroups(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordJson('composer.json', 'extra.pkg1', true);
        $recorder->recordJson('composer.json', 'extra.pkg2', true);

        $this->assertCount(2, $recorder->drain());
    }

    public function testBundleRecordsForSameFileAndIdentifierCoalesce(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordBundle('config/bundles.php', 'My\\Bundle', false);
        $recorder->recordBundle('config/bundles.php', 'My\\Bundle', true);

        $groups = $recorder->drain();

        $this->assertCount(1, $groups);
        $this->assertTrue($groups[0]['changed']);
    }

    public function testServiceRecordsForSameFileAndIdentifierCoalesce(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordService('config/services.yaml', 'My\\Service', true);
        $recorder->recordService('config/services.yaml', 'My\\Service', true);

        $this->assertCount(1, $recorder->drain());
    }

    public function testCoalescePreservesOrderOfFirstOccurrenceAcrossTypes(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordEnv('.env.example', ['A'], [], []);
        $recorder->recordCopy('.ddev/commands/web', 'x', true);
        $recorder->recordEnv('.env.example', ['B'], [], []);
        $recorder->recordCopy('.ddev/commands/web', 'y', true);

        $groups = $recorder->drain();

        $this->assertCount(2, $groups);
        // Env keeps the slot of its first occurrence — before the copy group.
        $this->assertSame('env', $groups[0]['type']);
        $this->assertSame(['A', 'B'], $groups[0]['added']);
        $this->assertSame('copy', $groups[1]['type']);
        $this->assertSame(['x', 'y'], $groups[1]['added']);
    }

    public function testInterleavedRecordsForDifferentTargetsProduceOneGroupEach(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordEnv('.env.example', ['A'], [], []);
        $recorder->recordEnv('.env.local', ['B'], [], []);
        $recorder->recordEnv('.env.example', ['C'], [], []);
        $recorder->recordEnv('.env.local', ['D'], [], []);

        $groups = $recorder->drain();

        $this->assertCount(2, $groups);
        $this->assertSame('.env.example', $groups[0]['file']);
        $this->assertSame(['A', 'C'], $groups[0]['added']);
        $this->assertSame('.env.local', $groups[1]['file']);
        $this->assertSame(['B', 'D'], $groups[1]['added']);
    }
}
