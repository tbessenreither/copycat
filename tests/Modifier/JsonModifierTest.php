<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tbessenreither\Copycat\Modifier\JsonModifier;
use Tbessenreither\Copycat\Tests\TestCase;
use Throwable;

#[CoversClass(JsonModifier::class)]
class JsonModifierTest extends TestCase
{
    private JsonModifier $jsonModifier;
    private string $testFileContent;

    public function setup(): void
    {
        parent::setUp();
        $this->jsonModifier = new JsonModifier();
        $this->testFileContent = $this->loadTestFile('example.json');
    }

    public function testAddNoOverwrite(): void
    {
        ob_start();
        $modifiedContent = $this->jsonModifier->add(
            fileContent: $this->testFileContent,
            path: 'newKey.subkey',
            value: [
                'my' => 'value',
                'int' => 123,
                'bool' => true,
                'null' => null,
            ],
            overwrite: false,
        );
        $output = ob_get_clean();

        $modifiedContentSimplified = explode("\n", $modifiedContent);
        $modifiedContentSimplified = array_map('trim', $modifiedContentSimplified);
        $modifiedContentSimplified = implode("\n", $modifiedContentSimplified);

        $expectedOverall = implode("\n", [
            '"subkey": {',
            '"my": "value",',
            '"int": 123,',
            '"bool": true,',
            '"null": null',
            '}',
        ]);

        $this->assertStringContainsString($expectedOverall, $modifiedContentSimplified);
        $this->assertStringNotContainsString('lowercase_var=lowercase_value', $modifiedContentSimplified);

    }

    #[DataProvider('provideTestDataForAdd')]

    public function testAutomatedForAdd(
        string $fileContent,
        string $path,
        mixed $value,
        bool $overwrite,
        string $expected,
        false|string $expectException,
    ): void {
        if ($expectException !== false) {
            $this->expectExceptionMessage($expectException);
        }

        try {
            ob_start();
            $modifiedContent = $this->jsonModifier->add(
                fileContent: $fileContent,
                path: $path,
                value: $value,
                overwrite: $overwrite,
            );
        } catch (Throwable $e) {
            throw $e;
        } finally {
            ob_end_clean();
        }

        $modifiedContentFlat = $this->flattenJsonString($modifiedContent);

        $this->assertSame($expected, $modifiedContentFlat);
    }

    #[DataProvider('provideTestDataForRemove')]

    public function testAutomatedForRemove(
        string $fileContent,
        string $path,
        string $expected,
        false|string $expectException,
    ): void {
        if ($expectException !== false) {
            $this->expectExceptionMessage($expectException);
        }

        try {
            ob_start();
            $modifiedContent = $this->jsonModifier->remove(
                fileContent: $fileContent,
                path: $path,
            );
        } catch (Throwable $e) {
            throw $e;
        } finally {
            ob_end_clean();
        }

        $modifiedContentFlat = $this->flattenJsonString($modifiedContent);

        $this->assertSame($expected, $modifiedContentFlat);
    }

    private function flattenJsonString(string $jsonString): string
    {
        $jsonDecoded = json_decode($jsonString, true);
        $jsonFlat = json_encode($jsonDecoded, JSON_UNESCAPED_SLASHES);

        return $jsonFlat;
    }

    public static function provideTestDataForAdd(): array
    {
        return [
            [
                'fileContent' => '{}',
                'path' => 'newKey',
                'value' => 'newValue',
                'overwrite' => false,
                'expected' => '{"newKey":"newValue"}',
                'expectException' => false,
            ],
            [
                'fileContent' => '{"existingKey":"existingValue"}',
                'path' => 'existingKey',
                'value' => 'newValue',
                'overwrite' => false,
                'expected' => '{"existingKey":"existingValue"}',
                'expectException' => 'Cannot add value at path "existingKey" because there is already a ',
            ],
            [
                'fileContent' => '{"existingKey":"existingValue"}',
                'path' => 'existingKey',
                'value' => 'newValue',
                'overwrite' => true,
                'expected' => '{"existingKey":"newValue"}',
                'expectException' => false,
            ],
            [
                'fileContent' => '{"nested":{"key":"value"}}',
                'path' => 'nested.newKey',
                'value' => 'newValue',
                'overwrite' => false,
                'expected' => '{"nested":{"key":"value","newKey":"newValue"}}',
                'expectException' => false,
            ],
            [
                'fileContent' => '{"nested":{"key":"value"}}',
                'path' => 'nested.key',
                'value' => 'newValue',
                'overwrite' => false,
                'expected' => '{"nested":{"key":"value"}}',
                'expectException' => 'Cannot add value at path "nested.key" because there is already a value',
            ],
            [
                'fileContent' => '{"nested":{"key":"value"}}',
                'path' => 'nested.key',
                'value' => 'newValue',
                'overwrite' => true,
                'expected' => '{"nested":{"key":"newValue"}}',
                'expectException' => false,
            ],
        ];
    }

    public static function provideTestDataForRemove(): array
    {
        return [
            [
                'fileContent' => '{"key":"value"}',
                'path' => 'key',
                'expected' => '[]',
                'expectException' => false,
            ],
            [
                'fileContent' => '{"nested":{"key":"value"}}',
                'path' => 'nested.key',
                'expected' => '{"nested":[]}',
                'expectException' => false,
            ],
            [
                'fileContent' => '{"nested":{"key":"value"}}',
                'path' => 'nonexistent',
                'expected' => '{"nested":{"key":"value"}}',
                'expectException' => 'No entry found at path "nonexistent", skipping.',
            ],
            [
                'fileContent' => '{"nested":{"key":"value"}}',
                'path' => 'nested.nonexistent',
                'expected' => '{"nested":{"key":"value"}}',
                'expectException' => 'No entry found at path "nested.nonexistent", skipping.',
            ],
            [
                'fileContent' => '{"nested":{"key":"value"}}',
                'path' => 'nested',
                'expected' => '[]',
                'expectException' => false,
            ],
        ];
    }

}
