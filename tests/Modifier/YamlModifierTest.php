<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tbessenreither\Copycat\Modifier\YamlModifier;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(YamlModifier::class)]
class YamlModifierTest extends TestCase
{
    public function testValueFormater(): void
    {
        $this->assertEquals('simple string', YamlModifier::formatValue('simple string'));
        $this->assertEquals('"string with special chars: {}[]\""', YamlModifier::formatValue('string with special chars: {}[]"'));
        $this->assertEquals('true', YamlModifier::formatValue(true));
        $this->assertEquals('false', YamlModifier::formatValue(false));
        $this->assertEquals('null', YamlModifier::formatValue(null));
        $this->assertEquals('123', YamlModifier::formatValue(123));
    }

    public function testGetIndentString(): void
    {
        $this->assertEquals('', YamlModifier::indentationString(0, 4));
        $this->assertEquals('    ', YamlModifier::indentationString(1, 4));
        $this->assertEquals('        ', YamlModifier::indentationString(2, 4));
        $this->assertEquals('  ', YamlModifier::indentationString(1, 2));
    }

    public function testArrayToYaml(): void
    {
        $array = [
            'name' => 'John Doe',
            'age' => 30,
            'is_active' => true,
            'address' => [
                'street' => '123 Main St',
                'city' => 'Anytown',
                'zip' => '12345'
            ],
            'notes' => null
        ];

        $expectedYaml = <<<YAML
name: John Doe
age: 30
is_active: true
address:
    street: 123 Main St
    city: Anytown
    zip: 12345
notes: null

YAML;

        $this->assertEquals($expectedYaml, YamlModifier::arrayToYaml($array, 0, 4));
    }

    public function testArrayToYamlWithDifferentIndentation(): void
    {
        $array = [
            'name' => 'John Doe',
            'age' => 30,
            'is_active' => true,
            'address' => [
                'street' => '123 Main St',
                'city' => 'Anytown',
                'zip' => '12345'
            ],
            'notes' => null
        ];

        $expectedYaml = <<<YAML
   name: John Doe
   age: 30
   is_active: true
   address:
      street: 123 Main St
      city: Anytown
      zip: 12345
   notes: null

YAML;

        $this->assertEquals($expectedYaml, YamlModifier::arrayToYaml($array, 1, 3));
    }

    public function testGenerationOfServiceYamlEntry(): void
    {
        $structure = [
            'App\\Service\\MyService' => [
                'class' => 'App\\Service\\MyService',
                'public' => true,
                'decorates' => 'App\\Service\\BaseService',
                'arguments' => [
                    '$var1' => '@some_dependency',
                    '$var2' => '%env(MY_ENV_VAR)%',
                ],
                'tags' => [
                    ['name' => 'some.tag', 'attribute' => 'value'],
                ],
            ],
            'App\\' => [
                'resource' => '../src/',
                'exclude' => [
                    '../src/DependencyInjection/',
                    '../src/Entity/',
                    '../src/Kernel.php',
                ],
            ],
        ];

        $expectedYaml = <<<YAML
    App\Service\MyService:
        class: App\Service\MyService
        public: true
        decorates: App\Service\BaseService
        arguments:
            \$var1: "@some_dependency"
            \$var2: "%env(MY_ENV_VAR)%"
        tags:
            - {
                name: some.tag
                attribute: value
              }
    App\:
        resource: "../src/"
        exclude:
            - "../src/DependencyInjection/"
            - "../src/Entity/"
            - "../src/Kernel.php"

YAML;

        $this->assertEquals($expectedYaml, YamlModifier::arrayToYaml($structure, 1, 4));
    }

    public function testFindStartingLineWithoutMatch(): void
    {
        $this->assertEquals(0, YamlModifier::findStartingLine(['no indentation'], 0));
    }

    public function testFindClosingLineWithoutMatch(): void
    {
        $this->assertEquals(0, YamlModifier::findClosingLine(['no indentation'], 0));
    }

    public function testGetIndentLength(): void
    {
        $this->assertEquals(0, YamlModifier::getIndentLength('no indentation'));
        $this->assertEquals(4, YamlModifier::getIndentLength('    four spaces'));
        $this->assertEquals(8, YamlModifier::getIndentLength('        eight spaces'));
        $this->assertEquals(2, YamlModifier::getIndentLength('  two spaces'));
    }

    public function testFindClosingLine(): void
    {
        $fileContent = file_get_contents(__DIR__ . '/../TestFiles/services.yaml');
        $lines = explode(PHP_EOL, $fileContent);

        $this->assertEquals(12, YamlModifier::findClosingLine($lines, 6), 'Failed to find the closing line for parameters (#9)');
        $this->assertEquals(27, YamlModifier::findClosingLine($lines, 21), 'Failed to find the closing line for App\ (#22)');
        $this->assertEquals(34, YamlModifier::findClosingLine($lines, 30), 'Failed to find the closing line for OllamaEmbeddingGenerator (#31)');
        $this->assertEquals(53, YamlModifier::findClosingLine($lines, 13), 'Failed to find the closing line for services (#14)');
    }

    public function testCleanObjectFromLines(): void
    {
        $fileContent = file_get_contents(__DIR__ . '/../TestFiles/services.yaml');
        $lines = explode(PHP_EOL, $fileContent);

        $objectLines = YamlModifier::cleanObjectFromLines($lines, ['App\\', 'Meilisearch\Client']);
        $objectString = implode(PHP_EOL, $objectLines);

        $linesNoLongerInObject = [
            '# makes classes in src/ available to be used as services',
            'App\\:',
            'resource: "../src/"',
            '- "../src/Entity/"',
            'Meilisearch\Client:',
            '$url: "%env(MEILISEARCH_URL)%"',
            '$apiKey: "%env(MEILISEARCH_KEY)%"',
        ];

        foreach ($linesNoLongerInObject as $line) {
            $this->assertStringNotContainsString($line, $objectString, "Failed to clean object from lines. Line still present: $line");
        }

        $linesStillExisting = [
            'autoconfigure: true # Automatically registers your services',
            '# add more service definitions when explicit',
            '$ollamaModel: "%env(OLLAMA_MODEL)%"',
            'App\Resource\Search\Service\MeilisearchService:',
        ];
        foreach ($linesStillExisting as $line) {
            $this->assertStringContainsString($line, $objectString, "Failed to clean object from lines. Line should still be present but is not: $line");
        }

    }

    public function testInsertArrayIntoObject(): void
    {
        $fileContent = file_get_contents(__DIR__ . '/../TestFiles/services.yaml');
        $lines = explode(PHP_EOL, $fileContent);

        $newEntry = [
            'App\\Service\\NewService' => [
                'class' => 'App\\Service\\NewService',
                'public' => true,
            ],
            'Meilisearch\Client' => [
                'class' => 'Meilisearch\\Client',
                'arguments' => [
                    '%env(MEILISEARCH_HOST)%',
                    '%env(MEILISEARCH_KEY)%',
                ],
            ],
        ];

        $inserted = YamlModifier::insertArrayIntoObject(
            lines: $lines,
            insertBlockKey: 'services',
            insertArray: $newEntry,
        );

        $insertedString = implode(PHP_EOL, $inserted);
        $this->assertStringContainsString('App\\Service\\NewService:', $insertedString);
        $this->assertStringContainsString('class: App\\Service\\NewService', $insertedString);
        $this->assertStringContainsString('Meilisearch\\Client:', $insertedString);
        $this->assertStringContainsString('class: Meilisearch\\Client', $insertedString);
        $this->assertStringContainsString('- "%env(MEILISEARCH_HOST)%"', $insertedString);
        $this->assertStringContainsString('- "%env(MEILISEARCH_KEY)%"', $insertedString);

        $newEntryString = YamlModifier::arrayToYaml($newEntry, 1, 4, true);
        $this->assertStringContainsString($newEntryString, $insertedString);
    }

    public function testInsertArrayIntoObjectExceptionBecauseInvalidInsertBlockKey(): void
    {
        $this->expectException(RuntimeException::class);

        $fileContent = file_get_contents(__DIR__ . '/../TestFiles/services.yaml');
        $lines = explode(PHP_EOL, $fileContent);

        $newEntry = [
            'App\\Service\\NewService' => [
                'class' => 'App\\Service\\NewService',
                'public' => true,
            ],
            'Meilisearch\Client' => [
                'class' => 'Meilisearch\\Client',
                'arguments' => [
                    '%env(MEILISEARCH_HOST)%',
                    '%env(MEILISEARCH_KEY)%',
                ],
            ],
        ];

        YamlModifier::insertArrayIntoObject(
            lines: $lines,
            insertBlockKey: 'services_non_existent',
            insertArray: $newEntry,
        );
    }

}
