<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionProperty;
use Tbessenreither\Copycat\Dto\PackageInfo;
use Tbessenreither\Copycat\Enum\VerbosityEnum;
use Tbessenreither\Copycat\Service\ConsoleOutput;
use Tbessenreither\Copycat\Service\Copycat;
use Tbessenreither\Copycat\Tests\TestCase;

/**
 * End-to-end tests for the grouped-collector output pipeline in {@see Copycat::flush()}.
 *
 * These tests bypass the modifier layer (already covered by
 * {@see \Tbessenreither\Copycat\Tests\Modifier\EnvModifierTest} et al.) by
 * seeding the private ops timeline directly via reflection. That keeps them
 * hermetic: no temporary project directory, no file I/O, only the layout
 * decisions in the emit methods under test.
 */
#[CoversClass(Copycat::class)]
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

    public function testEnvNormalInlineWithFewReplacesFitsOnSingleLine(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            $this->envOp('.env.example', [], ['APP_ENV', 'STAGE'], []),
        ]);

        $this->assertStringContainsString('• .env.example: ↻ APP_ENV, ↻ STAGE', $out);
        $this->assertStringNotContainsString('    ↻ APP_ENV', $out);
    }

    public function testEnvNormalFallsBackToMultilineWhenExceedingEntryThreshold(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            $this->envOp('.env.example', ['A', 'B', 'C', 'D', 'E', 'F'], [], []),
        ]);

        // Inline fallback triggers when > 5 entries — the file heading appears
        // on its own line and each name is indented.
        $this->assertStringContainsString('• .env.example', $out);
        $this->assertStringNotContainsString('.env.example:', $out);
        $this->assertStringContainsString('        + A', $out);
        $this->assertStringContainsString('        + F', $out);
    }

    public function testEnvNormalCollapsesSkipsOnlyCaseToSingleLine(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            $this->envOp('.env.local', [], [], ['APP_ENV', 'STAGE']),
        ]);

        $this->assertStringContainsString('• .env.local: 2 already set — skipped', $out);
        $this->assertStringNotContainsString('APP_ENV', $out);
        $this->assertStringNotContainsString('⏭', $out);
    }

    public function testEnvNormalAppendsCollapsedSkipCountToInlineForm(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            $this->envOp('.env.example', ['NEW_VAR'], ['STAGE'], ['SKIPPED_1', 'SKIPPED_2']),
        ]);

        $this->assertStringContainsString('• .env.example: + NEW_VAR, ↻ STAGE (2 already set)', $out);
    }

    public function testEnvVerboseListsEveryOutcomeIncludingSkips(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::VERBOSE, [
            $this->envOp('.env.example', ['NEW_VAR'], ['STAGE'], ['SKIPPED_1']),
        ]);

        $this->assertStringContainsString('• .env.example', $out);
        $this->assertStringContainsString('        + NEW_VAR', $out);
        $this->assertStringContainsString('        ↻ STAGE', $out);
        $this->assertStringContainsString('        ⏭ SKIPPED_1', $out);
        // Verbose should not use the inline form regardless of count.
        $this->assertStringNotContainsString('.env.example:', $out);
    }

    public function testCopyNormalCollapsesToCountWhenExceedingThreshold(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            $this->copyOp('.kiro/steering', 'a.md'),
            $this->copyOp('.kiro/steering', 'b.md'),
            $this->copyOp('.kiro/steering', 'c.md'),
            $this->copyOp('.kiro/steering', 'd.md'),
            $this->copyOp('.kiro/steering', 'e.md'),
            $this->copyOp('.kiro/steering', 'f.md'),
        ]);

        $this->assertStringContainsString('• .kiro/steering/ — 6 copied', $out);
        $this->assertStringNotContainsString('a.md', $out);
    }

    public function testCopyNormalInlineListsFilenamesWhenBelowThreshold(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            $this->copyOp('.kiro/settings', 'cli.json'),
            $this->copyOp('.kiro/settings', 'mcp.json'),
        ]);

        $this->assertStringContainsString('• .kiro/settings/: cli.json, mcp.json', $out);
    }

    public function testCopyNormalStaysSilentWhenAllDestinationsAlreadyExist(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            $this->copySkipOp('.git/hooks', 'pre-commit'),
            $this->copySkipOp('.git/hooks', 'pre-push'),
        ]);

        // All copies were no-ops → the fallback "(no changes)" line kicks in
        // because emit returned false for every group.
        $this->assertStringContainsString('(no changes)', $out);
    }

    public function testCopyVerboseListsFilenamesAndSkippedCount(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::VERBOSE, [
            $this->copyOp('.', '.php-cs-fixer.dist.php'),
            $this->copyOp('.', 'phpstan.dist.neon'),
            $this->copySkipOp('.', 'phpstan.neon'),
        ]);

        $this->assertStringContainsString('• ./', $out);
        $this->assertStringContainsString('        + .php-cs-fixer.dist.php', $out);
        $this->assertStringContainsString('        + phpstan.dist.neon', $out);
        $this->assertStringContainsString('(1 already exists — skipped)', $out);
    }

    public function testIgnoreNormalReportsAddedCountAndSilentOnZeroAdds(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            $this->ignoreOp('.gitignore', ['foo/'], []),
            $this->ignoreOp('.gitignore', ['bar/'], []),
            // A separate all-skip call for a different file must stay silent.
            $this->ignoreOp('.dockerignore', [], ['already-there']),
        ]);

        $this->assertStringContainsString('• .gitignore — 2 added', $out);
        $this->assertStringNotContainsString('.dockerignore', $out);
    }

    public function testIgnoreVerboseListsAddedEntriesUnderFileHeading(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::VERBOSE, [
            $this->ignoreOp('.gitignore', ['foo/', 'bar/'], ['already-here']),
        ]);

        $this->assertStringContainsString('• .gitignore', $out);
        $this->assertStringContainsString('        + foo/', $out);
        $this->assertStringContainsString('        + bar/', $out);
        $this->assertStringContainsString('(1 already present)', $out);
    }

    public function testJsonNormalNamesTargetFileAndPathOnSuccess(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            [
                'type' => 'json',
                'file' => 'composer.json',
                'path' => 'extra.mypkg.foo',
                'changed' => true,
            ],
        ]);

        $this->assertStringContainsString('• composer.json: added extra.mypkg.foo', $out);
    }

    public function testJsonNormalStaysSilentOnAlreadyPresent(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            [
                'type' => 'json',
                'file' => 'composer.json',
                'path' => 'extra.copycat',
                'changed' => false,
            ],
        ]);

        $this->assertStringContainsString('(no changes)', $out);
    }

    public function testBundleNormalStaysSilentOnAlreadyRegistered(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            [
                'type' => 'bundle',
                'file' => 'config/bundles.php',
                'identifier' => 'My\\Vendor\\SomeBundle',
                'changed' => false,
            ],
        ]);

        $this->assertStringContainsString('(no changes)', $out);
    }

    public function testBundleVerboseSurfacesAlreadyRegisteredAsSkipLine(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::VERBOSE, [
            [
                'type' => 'bundle',
                'file' => 'config/bundles.php',
                'identifier' => 'My\\Vendor\\SomeBundle',
                'changed' => false,
            ],
        ]);

        $this->assertStringContainsString('• config/bundles.php', $out);
        $this->assertStringContainsString('⏭ My\\Vendor\\SomeBundle (already registered)', $out);
    }

    public function testCoalesceMergesConsecutiveEnvCallsForSameFile(): void
    {
        // Two envAdd calls to the same file (e.g. one from CopycatConfig plus
        // one from an internal call) collapse into a single emitted summary.
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            $this->envOp('.env.example', ['FOO'], [], []),
            $this->envOp('.env.example', ['BAR'], [], []),
        ]);

        $this->assertSame(
            1,
            substr_count($out, '• .env.example'),
            'Two ops targeting the same env file should collapse into one line.',
        );
        $this->assertStringContainsString('+ FOO', $out);
        $this->assertStringContainsString('+ BAR', $out);
    }

    public function testCoalescePreservesOrderOfFirstOccurrence(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            $this->envOp('.env.example', ['A'], [], []),
            $this->copyOp('.ddev/commands/web', 'x'),
            $this->envOp('.env.example', ['B'], [], []),
            $this->copyOp('.ddev/commands/web', 'y'),
        ]);

        // Both env ops merge under the first slot, both copies merge under
        // the second — resulting order is env-then-copy, not interleaved.
        $envPos = strpos($out, '.env.example');
        $copyPos = strpos($out, '.ddev/commands/web');
        $this->assertNotFalse($envPos);
        $this->assertNotFalse($copyPos);
        $this->assertLessThan($copyPos, $envPos);
    }

    public function testFlushEmitsNoChangesFallbackWhenAllGroupsAreQuiet(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            // 0 adds, N skips at NORMAL renders nothing for ignore.
            $this->ignoreOp('.gitignore', [], ['a', 'b']),
        ]);

        $this->assertStringContainsString('(no changes)', $out);
    }

    public function testFlushDoesNotEmitNoChangesWhenAtLeastOneGroupRenders(): void
    {
        $out = $this->flushWithOps(VerbosityEnum::NORMAL, [
            $this->envOp('.env.dev', [], ['STAGE'], []),
        ]);

        $this->assertStringNotContainsString('(no changes)', $out);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $ops
     */
    private function flushWithOps(VerbosityEnum $verbosity, array $ops): string
    {
        ConsoleOutput::setVerbosity($verbosity);
        ConsoleOutput::setColorsEnabled(false);
        ConsoleOutput::startCapture();

        $copycat = new Copycat(
            packageInfo: new PackageInfo(
                namespace: 'Test\\Package',
                projectPath: '/tmp/copycat-test',
                autoloadPath: '/tmp/copycat-test/vendor/test/package',
                packagePath: '/tmp/copycat-test/vendor/test/package',
                composerName: 'test/package',
            ),
            projectRoot: '/tmp/copycat-test',
        );

        $opsProp = new ReflectionProperty(Copycat::class, 'ops');
        $opsProp->setValue($copycat, $ops);

        $copycat->flush();

        return ConsoleOutput::stopCapture();
    }

    /**
     * @param string[] $added
     * @param string[] $replaced
     * @param string[] $skipped
     * @return array<string, mixed>
     */
    private function envOp(string $file, array $added, array $replaced, array $skipped): array
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
     * @return array<string, mixed>
     */
    private function copyOp(string $targetDir, string $filename): array
    {
        return [
            'type' => 'copy',
            'targetDir' => $targetDir,
            'added' => [$filename],
            'skipped' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function copySkipOp(string $targetDir, string $filename): array
    {
        return [
            'type' => 'copy',
            'targetDir' => $targetDir,
            'added' => [],
            'skipped' => [$filename],
        ];
    }

    /**
     * @param string[] $added
     * @param string[] $skipped
     * @return array<string, mixed>
     */
    private function ignoreOp(string $file, array $added, array $skipped): array
    {
        return [
            'type' => 'ignore',
            'file' => $file,
            'added' => $added,
            'skipped' => $skipped,
        ];
    }
}
