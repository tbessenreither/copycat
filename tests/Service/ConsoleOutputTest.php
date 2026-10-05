<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tbessenreither\Copycat\Enum\VerbosityEnum;
use Tbessenreither\Copycat\Service\ConsoleOutput;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(ConsoleOutput::class)]
#[UsesClass(VerbosityEnum::class)]
class ConsoleOutputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConsoleOutput::reset();
        // Neutral baseline: colors off so assertions stay ANSI-free,
        // env var unset so verbosity resolves to its NORMAL default.
        ConsoleOutput::setColorsEnabled(false);
        putenv('COPYCAT_VERBOSITY');
        ConsoleOutput::startCapture();
    }

    protected function tearDown(): void
    {
        ConsoleOutput::stopCapture();
        ConsoleOutput::reset();
        putenv('COPYCAT_VERBOSITY');
        parent::tearDown();
    }

    public function testHidesMessagesBelowConfiguredLevel(): void
    {
        ConsoleOutput::setVerbosity(VerbosityEnum::NORMAL);
        ConsoleOutput::info('shown at normal');
        ConsoleOutput::verbose('hidden at normal');
        ConsoleOutput::debug('hidden at normal');

        $output = ConsoleOutput::stopCapture();

        $this->assertStringContainsString('shown at normal', $output);
        $this->assertStringNotContainsString('hidden at normal', $output);
    }

    public function testVerboseLevelRevealsVerboseButKeepsDebugHidden(): void
    {
        ConsoleOutput::setVerbosity(VerbosityEnum::VERBOSE);
        ConsoleOutput::verbose('per-file detail');
        ConsoleOutput::debug('trace only');

        $output = ConsoleOutput::stopCapture();

        $this->assertStringContainsString('per-file detail', $output);
        $this->assertStringNotContainsString('trace only', $output);
    }

    public function testErrorSurvivesSilentVerbosity(): void
    {
        ConsoleOutput::setVerbosity(VerbosityEnum::SILENT);
        ConsoleOutput::warning('muted');
        ConsoleOutput::info('muted');
        ConsoleOutput::error('boom');

        $output = ConsoleOutput::stopCapture();

        $this->assertStringNotContainsString('muted', $output);
        $this->assertStringContainsString('boom', $output);
    }

    public function testErrorIsPrefixedWithCrossSymbol(): void
    {
        ConsoleOutput::error('nope');

        $this->assertStringContainsString('✖ nope', ConsoleOutput::stopCapture());
    }

    public function testWarningIsPrefixedWithWarningSymbol(): void
    {
        ConsoleOutput::warning('careful');

        $this->assertStringContainsString('⚠ careful', ConsoleOutput::stopCapture());
    }

    public function testInlineBoldTagsRenderAsAnsiWhenColorsEnabled(): void
    {
        ConsoleOutput::setColorsEnabled(true);
        ConsoleOutput::info('a <b>b</b> c');

        // \033[1m turns bold on, \033[22m turns it off without touching color.
        $this->assertStringContainsString("a \033[1mb\033[22m c", ConsoleOutput::stopCapture());
    }

    public function testInlineBoldTagsAreStrippedWhenColorsDisabled(): void
    {
        ConsoleOutput::info('a <b>b</b> c');

        $output = ConsoleOutput::stopCapture();

        $this->assertStringContainsString('a b c', $output);
        $this->assertStringNotContainsString('<b>', $output);
        $this->assertStringNotContainsString('</b>', $output);
    }

    public function testInlineDimTagsRenderAsAnsiWhenColorsEnabled(): void
    {
        ConsoleOutput::setColorsEnabled(true);
        ConsoleOutput::info('<dim>+ created</dim> path.txt');

        // \033[2m turns dim on, \033[22m clears intensity attributes.
        $this->assertStringContainsString("\033[2m+ created\033[22m path.txt", ConsoleOutput::stopCapture());
    }

    public function testInlineDimTagsAreStrippedWhenColorsDisabled(): void
    {
        ConsoleOutput::info('<dim>+ created</dim> path.txt');

        $output = ConsoleOutput::stopCapture();

        $this->assertStringContainsString('+ created path.txt', $output);
        $this->assertStringNotContainsString('<dim>', $output);
        $this->assertStringNotContainsString('</dim>', $output);
    }

    public function testEnvironmentVariableSetsDefaultVerbosity(): void
    {
        putenv('COPYCAT_VERBOSITY=verbose');
        ConsoleOutput::reset();
        ConsoleOutput::setColorsEnabled(false);

        $this->assertSame(VerbosityEnum::VERBOSE, ConsoleOutput::getVerbosity());
    }

    public function testIndentParameterAddsFourSpacesPerLevel(): void
    {
        ConsoleOutput::info('root');
        ConsoleOutput::info('nested', 2);

        $output = ConsoleOutput::stopCapture();

        $this->assertStringContainsString("root\n", $output);
        $this->assertStringContainsString("        nested\n", $output);
    }

    public function testCaptureBufferInterceptsOutput(): void
    {
        ConsoleOutput::info('into the buffer');

        $captured = ConsoleOutput::stopCapture();

        $this->assertStringContainsString('into the buffer', $captured);
        // Nothing should reach real stdout during the test — expectOutputString('')
        // asserts that. Re-open a fresh capture so tearDown's stopCapture() has one.
        $this->expectOutputString('');
        ConsoleOutput::startCapture();
    }
}
