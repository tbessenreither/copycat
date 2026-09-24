<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tbessenreither\Copycat\Dto\PackageInfo;
use Tbessenreither\Copycat\Enum\VerbosityEnum;
use Tbessenreither\Copycat\Service\ConsoleOutput;
use Tbessenreither\Copycat\Service\Copycat;
use Tbessenreither\Copycat\Service\OperationPrinter;
use Tbessenreither\Copycat\Service\OperationRecorder;
use Tbessenreither\Copycat\Tests\TestCase;

/**
 * Smoke tests for the recorder → printer wiring inside `Copycat::flush()`.
 *
 * Detailed coalescence and layout behavior are covered by
 * {@see OperationRecorderTest} and {@see OperationPrinterTest} respectively;
 * this file only exercises that `Copycat` correctly drains its recorder into
 * its printer and applies the package-level `(no changes)` fallback.
 */
#[CoversClass(Copycat::class)]
#[UsesClass(OperationRecorder::class)]
#[UsesClass(OperationPrinter::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(VerbosityEnum::class)]
#[UsesClass(PackageInfo::class)]
class CopycatEmitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConsoleOutput::reset();
        ConsoleOutput::setColorsEnabled(false);
        putenv('COPYCAT_VERBOSITY');
    }

    protected function tearDown(): void
    {
        ConsoleOutput::reset();
        putenv('COPYCAT_VERBOSITY');
        parent::tearDown();
    }

    public function testFlushDrainsRecorderIntoPrinterAndCoalescesRelatedRecords(): void
    {
        $recorder = new OperationRecorder();
        // Two envAdd calls to the same file (e.g. one from CopycatConfig plus
        // one from an internal call) collapse into a single emitted summary.
        $recorder->recordEnv('.env.example', ['FOO'], [], []);
        $recorder->recordEnv('.env.example', ['BAR'], [], []);

        $out = $this->flushWith($recorder, VerbosityEnum::NORMAL);

        $this->assertSame(
            1,
            substr_count($out, '• .env.example'),
            'Two records targeting the same env file should coalesce into one line.',
        );
        $this->assertStringContainsString('+ FOO', $out);
        $this->assertStringContainsString('+ BAR', $out);
    }

    public function testFlushEmitsNoChangesFallbackWhenEveryGroupIsSilentAtNormal(): void
    {
        $recorder = new OperationRecorder();
        // 0 adds + N skips renders nothing for ignore at NORMAL.
        $recorder->recordIgnore('.gitignore', [], ['already', 'here']);

        $out = $this->flushWith($recorder, VerbosityEnum::NORMAL);

        $this->assertStringContainsString('(no changes)', $out);
    }

    public function testFlushDoesNotEmitNoChangesWhenAtLeastOneGroupRenders(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordEnv('.env.dev', [], ['STAGE'], []);

        $out = $this->flushWith($recorder, VerbosityEnum::NORMAL);

        $this->assertStringNotContainsString('(no changes)', $out);
    }

    public function testFlushEmitsNoChangesWhenRecorderIsCompletelyEmpty(): void
    {
        $recorder = new OperationRecorder();

        $out = $this->flushWith($recorder, VerbosityEnum::NORMAL);

        $this->assertStringContainsString('(no changes)', $out);
    }

    public function testFlushClearsRecorderSoSubsequentFlushIsIndependent(): void
    {
        $recorder = new OperationRecorder();
        $recorder->recordEnv('.env.example', ['FOO'], [], []);

        $copycat = $this->buildCopycat($recorder);

        ConsoleOutput::setVerbosity(VerbosityEnum::NORMAL);
        ConsoleOutput::setColorsEnabled(false);

        // First flush: renders the FOO record.
        ConsoleOutput::startCapture();
        $copycat->flush();
        $firstOut = ConsoleOutput::stopCapture();
        $this->assertStringContainsString('+ FOO', $firstOut);

        // Second flush without any new records: should fall through to
        // `(no changes)` — proving the recorder was drained on the first call.
        ConsoleOutput::startCapture();
        $copycat->flush();
        $secondOut = ConsoleOutput::stopCapture();
        $this->assertStringNotContainsString('FOO', $secondOut);
        $this->assertStringContainsString('(no changes)', $secondOut);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function flushWith(OperationRecorder $recorder, VerbosityEnum $verbosity): string
    {
        ConsoleOutput::setVerbosity($verbosity);
        ConsoleOutput::setColorsEnabled(false);
        ConsoleOutput::startCapture();

        $this->buildCopycat($recorder)->flush();

        return ConsoleOutput::stopCapture();
    }

    private function buildCopycat(OperationRecorder $recorder): Copycat
    {
        return new Copycat(
            packageInfo: new PackageInfo(
                namespace: 'Test\\Package',
                projectPath: '/tmp/copycat-test',
                autoloadPath: '/tmp/copycat-test/vendor/test/package',
                packagePath: '/tmp/copycat-test/vendor/test/package',
                composerName: 'test/package',
            ),
            projectRoot: '/tmp/copycat-test',
            recorder: $recorder,
        );
    }
}
