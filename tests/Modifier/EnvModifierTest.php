<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tbessenreither\Copycat\Dto\EnvVar;
use Tbessenreither\Copycat\Modifier\EnvModifier;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(EnvModifier::class)]
#[UsesClass(EnvVar::class)]
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

    public function testAddNoOverwrite(): void
    {
        ob_start();
        $modifiedContent = $this->envModifier->add(
            fileContent: $this->testFileContent,
            entries: $this->array2EnvVarArray([
                'TEST_ENV_VAR' => 'test value',
                'lowercase_var' => 'lowercase_value',
                'INT_VAR' => 123,
                'BOOL_VAR' => true,
                'NULL_VAR' => null,
                'STRING' => 'string value',
            ]),
            groupName: 'testgroup',
            overwrite: false,
        );
        ob_end_clean();

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

        $this->assertStringContainsString($expectedOverall, $modifiedContent);
        $this->assertStringNotContainsString('lowercase_var=lowercase_value', $modifiedContent);

        $modifiedContentLines = explode("\n", $modifiedContent);

        $stringLineIndex = array_search('STRING="a great string"', $modifiedContentLines);
        $this->assertNotFalse($stringLineIndex, 'line with key STRING not found');
        $this->assertGreaterThan(10, $stringLineIndex, 'STRING key should be somewhere at the end of the file');
    }

    public function testAddWithOverwrite(): void
    {
        ob_start();
        $modifiedContent = $this->envModifier->add(
            fileContent: $this->testFileContent,
            entries: $this->array2EnvVarArray([
                'STRING' => 'string value',
            ]),
            groupName: 'testgroup',
            overwrite: true,
        );
        ob_end_clean();

        $expectedOverall = implode("\n", [
            '###> testgroup',
            'STRING="string value"',
            '###< testgroup',
        ]);

        $this->assertStringContainsString($expectedOverall, $modifiedContent);
        $this->assertStringNotContainsString('lowercase_var=lowercase_value', $modifiedContent);

        $modifiedContentLines = explode("\n", $modifiedContent);

        $stringLineIndex = array_search('STRING="string value"', $modifiedContentLines);
        $this->assertNotFalse($stringLineIndex, 'line with key STRING not found');
        $this->assertGreaterThan(10, $stringLineIndex, 'STRING key should be somewhere at the end of the file');
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
