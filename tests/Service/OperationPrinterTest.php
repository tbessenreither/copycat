<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tbessenreither\Copycat\Enum\VerbosityEnum;
use Tbessenreither\Copycat\Service\ConsoleOutput;
use Tbessenreither\Copycat\Service\OperationPrinter;
use Tbessenreither\Copycat\Tests\TestCase;

/**
 * Unit tests for the presentation side of the recorder → printer pipeline.
 *
 * Each test feeds the printer a pre-built group array (matching the shape
 * {@see \Tbessenreither\Copycat\Service\OperationRecorder::drain()} returns)
 * and asserts the resulting CLI output plus the "did anything render?"
 * return value.
 *
 * The `(no changes)` fallback lives in `Copycat::flush()`, not here — it's
 * a package-level decision, not a per-group one — so these tests only
 * assert that emit() returns false when nothing renders, and the fallback
 * itself is covered by CopycatEmitTest.
 */
#[CoversClass(OperationPrinter::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(VerbosityEnum::class)]
class OperationPrinterTest extends TestCase
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

    // ------------------------------------------------------------------
    // env
    // ------------------------------------------------------------------

    public function testEnvNormalInlineWithFewReplacesFitsOnSingleLine(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            $this->envGroup('.env.example', [], ['APP_ENV', 'STAGE'], []),
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• .env.example: ↻ APP_ENV, ↻ STAGE', $out);
        $this->assertStringNotContainsString('    ↻ APP_ENV', $out);
    }

    public function testEnvNormalFallsBackToMultilineWhenExceedingEntryThreshold(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            $this->envGroup('.env.example', ['A', 'B', 'C', 'D', 'E', 'F'], [], []),
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• .env.example', $out);
        $this->assertStringNotContainsString('.env.example:', $out);
        $this->assertStringContainsString('        + A', $out);
        $this->assertStringContainsString('        + F', $out);
    }

    public function testEnvNormalCollapsesSkipsOnlyCaseToSingleLine(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            $this->envGroup('.env.local', [], [], ['APP_ENV', 'STAGE']),
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• .env.local: 2 already set — skipped', $out);
        $this->assertStringNotContainsString('APP_ENV', $out);
        $this->assertStringNotContainsString('⏭', $out);
    }

    public function testEnvNormalWithNoActivityAndNoSkipsIsSilentAndReturnsFalse(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            $this->envGroup('.env.example', [], [], []),
        ]);

        $this->assertFalse($emitted);
        $this->assertSame('', $out);
    }

    public function testEnvNormalAppendsCollapsedSkipCountToInlineForm(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            $this->envGroup('.env.example', ['NEW_VAR'], ['STAGE'], ['SKIPPED_1', 'SKIPPED_2']),
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• .env.example: + NEW_VAR, ↻ STAGE (2 already set)', $out);
    }

    public function testEnvVerboseListsEveryOutcomeIncludingSkips(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::VERBOSE, [
            $this->envGroup('.env.example', ['NEW_VAR'], ['STAGE'], ['SKIPPED_1']),
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• .env.example', $out);
        $this->assertStringContainsString('        + NEW_VAR', $out);
        $this->assertStringContainsString('        ↻ STAGE', $out);
        $this->assertStringContainsString('        ⏭ SKIPPED_1', $out);
        $this->assertStringNotContainsString('.env.example:', $out);
    }

    // ------------------------------------------------------------------
    // copy
    // ------------------------------------------------------------------

    public function testCopyNormalCollapsesToCountWhenExceedingThreshold(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            $this->copyGroup('.kiro/steering', ['a.md', 'b.md', 'c.md', 'd.md', 'e.md', 'f.md']),
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• .kiro/steering/ — 6 copied', $out);
        $this->assertStringNotContainsString('a.md', $out);
    }

    public function testCopyNormalInlineListsFilenamesWhenBelowThreshold(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            $this->copyGroup('.kiro/settings', ['cli.json', 'mcp.json']),
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• .kiro/settings/: cli.json, mcp.json', $out);
    }

    public function testCopyNormalStaysSilentWhenAllDestinationsAlreadyExist(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            $this->copyGroup('.git/hooks', [], ['pre-commit', 'pre-push']),
        ]);

        $this->assertFalse($emitted);
        $this->assertSame('', $out);
    }

    public function testCopyVerboseListsFilenamesAndSkippedCount(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::VERBOSE, [
            $this->copyGroup('.', ['.php-cs-fixer.dist.php', 'phpstan.dist.neon'], ['phpstan.neon']),
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• ./', $out);
        $this->assertStringContainsString('        + .php-cs-fixer.dist.php', $out);
        $this->assertStringContainsString('        + phpstan.dist.neon', $out);
        $this->assertStringContainsString('(1 already exists — skipped)', $out);
    }

    public function testCopyVerboseUsesPluralAlreadyExistWhenMultipleSkipped(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::VERBOSE, [
            $this->copyGroup('.', ['x'], ['y', 'z']),
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('(2 already exist — skipped)', $out);
    }

    // ------------------------------------------------------------------
    // ignore
    // ------------------------------------------------------------------

    public function testIgnoreNormalReportsAddedCount(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            $this->ignoreGroup('.gitignore', ['foo/', 'bar/'], []),
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• .gitignore — 2 added', $out);
    }

    public function testIgnoreNormalIsSilentAndReturnsFalseWhenNoAdds(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            $this->ignoreGroup('.dockerignore', [], ['already-there']),
        ]);

        $this->assertFalse($emitted);
        $this->assertSame('', $out);
    }

    public function testIgnoreVerboseListsAddedEntriesUnderFileHeading(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::VERBOSE, [
            $this->ignoreGroup('.gitignore', ['foo/', 'bar/'], ['already-here']),
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• .gitignore', $out);
        $this->assertStringContainsString('        + foo/', $out);
        $this->assertStringContainsString('        + bar/', $out);
        $this->assertStringContainsString('(1 already present)', $out);
    }

    public function testIgnoreVerboseWithOnlySkipsPrintsInlineSkipLine(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::VERBOSE, [
            $this->ignoreGroup('.gitignore', [], ['a', 'b']),
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• .gitignore — 0 added, 2 already present', $out);
    }

    // ------------------------------------------------------------------
    // json
    // ------------------------------------------------------------------

    public function testJsonNormalNamesTargetFileAndPathOnSuccess(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            ['type' => 'json', 'file' => 'composer.json', 'path' => 'extra.mypkg.foo', 'changed' => true],
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• composer.json: added extra.mypkg.foo', $out);
    }

    public function testJsonNormalIsSilentAndReturnsFalseOnAlreadyPresent(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            ['type' => 'json', 'file' => 'composer.json', 'path' => 'extra.copycat', 'changed' => false],
        ]);

        $this->assertFalse($emitted);
        $this->assertSame('', $out);
    }

    public function testJsonVerboseSurfacesAlreadyPresentAsSkipLine(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::VERBOSE, [
            ['type' => 'json', 'file' => 'composer.json', 'path' => 'extra.copycat', 'changed' => false],
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• composer.json', $out);
        $this->assertStringContainsString('⏭ extra.copycat (already present)', $out);
    }

    // ------------------------------------------------------------------
    // bundle
    // ------------------------------------------------------------------

    public function testBundleNormalPrintsSingleLineOnChange(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            ['type' => 'bundle', 'file' => 'config/bundles.php', 'identifier' => 'My\\Bundle', 'changed' => true],
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• config/bundles.php: + My\\Bundle', $out);
    }

    public function testBundleNormalIsSilentAndReturnsFalseOnAlreadyRegistered(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            ['type' => 'bundle', 'file' => 'config/bundles.php', 'identifier' => 'My\\Bundle', 'changed' => false],
        ]);

        $this->assertFalse($emitted);
        $this->assertSame('', $out);
    }

    public function testBundleVerboseSurfacesAlreadyRegisteredAsSkipLine(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::VERBOSE, [
            ['type' => 'bundle', 'file' => 'config/bundles.php', 'identifier' => 'My\\Vendor\\SomeBundle', 'changed' => false],
        ]);

        $this->assertTrue($emitted);
        $this->assertStringContainsString('• config/bundles.php', $out);
        $this->assertStringContainsString('⏭ My\\Vendor\\SomeBundle (already registered)', $out);
    }

    // ------------------------------------------------------------------
    // service
    // ------------------------------------------------------------------

    public function testServicePrintsSingleLineRegardlessOfVerbosity(): void
    {
        [$outNormal, $emittedNormal] = $this->emit(VerbosityEnum::NORMAL, [
            ['type' => 'service', 'file' => 'config/services.yaml', 'identifier' => 'My\\Service', 'changed' => true],
        ]);
        [$outVerbose, $emittedVerbose] = $this->emit(VerbosityEnum::VERBOSE, [
            ['type' => 'service', 'file' => 'config/services.yaml', 'identifier' => 'My\\Service', 'changed' => true],
        ]);

        $this->assertTrue($emittedNormal);
        $this->assertTrue($emittedVerbose);
        $this->assertStringContainsString('• config/services.yaml: + My\\Service', $outNormal);
        $this->assertStringContainsString('• config/services.yaml: + My\\Service', $outVerbose);
    }

    // ------------------------------------------------------------------
    // envelope
    // ------------------------------------------------------------------

    public function testEmitReturnsFalseForEmptyGroups(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, []);

        $this->assertFalse($emitted);
        $this->assertSame('', $out);
    }

    public function testEmitReturnsTrueWhenAtLeastOneGroupRenders(): void
    {
        [, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            $this->ignoreGroup('.gitignore', [], ['a', 'b']),   // silent at NORMAL
            $this->ignoreGroup('.gitignore-2', ['x'], []),      // prints one line
        ]);

        $this->assertTrue($emitted);
    }

    public function testUnknownGroupTypeIsIgnoredAndReturnsFalse(): void
    {
        [$out, $emitted] = $this->emit(VerbosityEnum::NORMAL, [
            ['type' => 'unknown-future-op', 'file' => 'whatever'],
        ]);

        $this->assertFalse($emitted);
        $this->assertSame('', $out);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $groups
     * @return array{0: string, 1: bool}
     */
    private function emit(VerbosityEnum $verbosity, array $groups): array
    {
        ConsoleOutput::setVerbosity($verbosity);
        ConsoleOutput::setColorsEnabled(false);
        ConsoleOutput::startCapture();

        $emitted = (new OperationPrinter())->emit($groups);

        return [ConsoleOutput::stopCapture(), $emitted];
    }

    /**
     * @param string[] $added
     * @param string[] $replaced
     * @param string[] $skipped
     * @return array<string, mixed>
     */
    private function envGroup(string $file, array $added, array $replaced, array $skipped): array
    {
        return [
            'type' => 'env',
            'file' => $file,
            'added' => $added,
            'replaced' => $replaced,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param string[] $added
     * @param string[] $skipped
     * @return array<string, mixed>
     */
    private function copyGroup(string $targetDir, array $added, array $skipped = []): array
    {
        return [
            'type' => 'copy',
            'targetDir' => $targetDir,
            'added' => $added,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param string[] $added
     * @param string[] $skipped
     * @return array<string, mixed>
     */
    private function ignoreGroup(string $file, array $added, array $skipped): array
    {
        return [
            'type' => 'ignore',
            'file' => $file,
            'added' => $added,
            'skipped' => $skipped,
        ];
    }
}
