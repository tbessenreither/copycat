<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tbessenreither\Copycat\Dto\EnvVar;
use Tbessenreither\Copycat\Enum\VerbosityEnum;
use Tbessenreither\Copycat\Modifier\EnvModifier;
use Tbessenreither\Copycat\Service\ConsoleOutput;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(EnvModifier::class)]
#[UsesClass(EnvVar::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(VerbosityEnum::class)]
class EnvModifierTest extends TestCase
{
    private EnvModifier $envModifier;
    private string $testFileContent;

    public function setup(): void
    {
        parent::setUp();
        $this->envModifier = new EnvModifier();
        $this->testFileContent = $this->loadTestFile('env.txt');
    }

    public function testAddNoOverwriteReturnsStatsAndContent(): void
    {
        $result = $this->envModifier->add(
            fileContent: $this->testFileContent,
            entries: $this->array2EnvVarArray([
                'TEST_ENV_VAR' => 'test value',
                'lowercase_var' => 'lowercase_value',
                'INT_VAR' => 123,
                'BOOL_VAR' => true,
                'NULL_VAR' => null,
                'STRING' => 'string value', // present in fixture → should be skipped
            ]),
            groupName: 'testgroup',
            overwrite: false,
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('added', $result);
        $this->assertArrayHasKey('replaced', $result);
        $this->assertArrayHasKey('skipped', $result);

        $expectedOverall = implode("\n", [
            '###> testgroup',
            'BOOL_VAR=true',
            'INT_VAR=123',
            'LOWERCASE_VAR=lowercase_value',
            'NULL_VAR=null',
            'STRING="a great string"',
            'TEST_ENV_VAR="test value"',
            '###< testgroup',
        ]);

        $this->assertStringContainsString($expectedOverall, $result['content']);
        $this->assertStringNotContainsString('lowercase_var=lowercase_value', $result['content']);

        $this->assertContains('TEST_ENV_VAR', $result['added']);
        $this->assertContains('LOWERCASE_VAR', $result['added']);
        $this->assertContains('INT_VAR', $result['added']);
        $this->assertContains('BOOL_VAR', $result['added']);
        $this->assertContains('NULL_VAR', $result['added']);
        $this->assertContains('STRING', $result['skipped']);
        $this->assertSame([], $result['replaced']);
    }

    public function testAddWithOverwriteReportsReplacedByName(): void
    {
        $result = $this->envModifier->add(
            fileContent: $this->testFileContent,
            entries: $this->array2EnvVarArray([
                'STRING' => 'string value',
            ]),
            groupName: 'testgroup',
            overwrite: true,
        );

        $expectedOverall = implode("\n", [
            '###> testgroup',
            'STRING="string value"',
            '###< testgroup',
        ]);

        $this->assertStringContainsString($expectedOverall, $result['content']);
        $this->assertStringNotContainsString('lowercase_var=lowercase_value', $result['content']);
        $this->assertSame(['STRING'], $result['replaced']);
        $this->assertSame([], $result['added']);
        $this->assertSame([], $result['skipped']);
    }

    public function testAddIsSilentUnderNormalVerbosity(): void
    {
        ConsoleOutput::reset();
        ConsoleOutput::setColorsEnabled(false);
        ConsoleOutput::setVerbosity(VerbosityEnum::NORMAL);
        ConsoleOutput::startCapture();

        $this->envModifier->add(
            fileContent: $this->testFileContent,
            entries: $this->array2EnvVarArray([
                'STRING' => 'string value',
            ]),
            groupName: 'testgroup',
            overwrite: true,
        );

        $captured = ConsoleOutput::stopCapture();
        ConsoleOutput::reset();

        // The modifier itself must not print anything user-facing anymore —
        // Copycat is responsible for the summary line.
        $this->assertSame('', $captured);
    }

    public function testRemove(): void
    {
        $modifiedContent = $this->envModifier->remove(
            fileContent: $this->testFileContent,
            groupName: 'SomeGroup',
        );

        $this->assertStringNotContainsString('ONE_VAR=', $modifiedContent);
        $this->assertStringNotContainsString('STRING=', $modifiedContent);
    }

    private function array2EnvVarArray(array $entries): array
    {
        $envVars = [];
        foreach ($entries as $key => $value) {

            if (!$value instanceof EnvVar) {
                $envVars[] = new EnvVar(name: $key, value: $value);
            } else {
                $envVars[] = $value;
            }
        }

        return array_values($envVars);
    }

}
