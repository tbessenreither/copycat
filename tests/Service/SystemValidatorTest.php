<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Service;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionMethod;
use RuntimeException;
use Tbessenreither\Copycat\Dto\PackageInfo;
use Tbessenreither\Copycat\Dto\SystemIndicator;
use Tbessenreither\Copycat\Enum\SystemIndicatorTypeEnum;
use Tbessenreither\Copycat\Service\SystemValidator;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(SystemValidator::class)]
#[UsesClass(PackageInfo::class)]
#[UsesClass(SystemIndicator::class)]
class SystemValidatorTest extends TestCase
{
    private const TEST_FILE_DIR = __DIR__ . '/SystemValidatorTestFiles/';
    private array $testVariables = [
        'SYSTEM_VALIDATOR_TEST_EXISTING_VAR' => 'i exist',
        'SYSTEM_VALIDATOR_TEST_BOOL_VAR_TRUE' => 'true',
        'SYSTEM_VALIDATOR_TEST_BOOL_VAR_FALSE' => 'false',
        'SYSTEM_VALIDATOR_TEST_NULL_VAR' => 'null',
    ];
    public function setUp(): void
    {
        foreach ($this->testVariables as $key => $value) {
            if ($value !== null) {
                $_ENV[$key] = $value;
            }
        }
    }

    public function tearDown(): void
    {
        foreach ($this->testVariables as $key => $value) {
            unset($_ENV[$key]);
        }
    }

    #[DataProvider('providetestCheckForEnvVariableData')]
    public function testCheckForEnvVariable(string $indicator, bool|string $expected): void
    {
        $reflectionMethod = new ReflectionMethod(SystemValidator::class, 'checkForEnvVariable');

        if (is_string($expected) && class_exists($expected)) {
            $this->expectException($expected);
            $reflectionMethod->invoke(null, $indicator);
        } else {
            $this->assertEquals($expected, $reflectionMethod->invoke(null, $indicator), 'Expected "' . $indicator . '" to be "' . ($expected ? 'true' : 'false') . '".');
        }
    }

    public static function providetestCheckForEnvVariableData(): Generator
    {
        yield ['SYSTEM_VALIDATOR_TEST_BOOL_VAR_TRUE=true', true];
        yield ['SYSTEM_VALIDATOR_TEST_BOOL_VAR_TRUE=false', false];

        yield ['SYSTEM_VALIDATOR_TEST_BOOL_VAR_FALSE=false', true];
        yield ['SYSTEM_VALIDATOR_TEST_BOOL_VAR_FALSE=true', false];

        yield ['SYSTEM_VALIDATOR_TEST_NULL_VAR=null', true];
        yield ['SYSTEM_VALIDATOR_TEST_NULL_VAR=notnull', false];

        yield ['SYSTEM_VALIDATOR_TEST_EXISTING_VAR=i exist', true];
        yield ['SYSTEM_VALIDATOR_TEST_EXISTING_VAR!=i exist', false];
        yield ['SYSTEM_VALIDATOR_TEST_EXISTING_VAR=some other value', false];
        yield ['SYSTEM_VALIDATOR_TEST_EXISTING_VAR!=some other value', true];

        yield ['exists:SYSTEM_VALIDATOR_TEST_EXISTING_VAR', true];
        yield ['!exists:SYSTEM_VALIDATOR_TEST_EXISTING_VAR', false];

        yield ['!exists:SYSTEM_VALIDATOR_TEST_NON_EXISTENT_VAR', true];
        yield ['exists:SYSTEM_VALIDATOR_TEST_NON_EXISTENT_VAR', false];

        // error case
        yield ['invalid indicator', RuntimeException::class];
    }

    #[DataProvider('providetestCheckIndicatorData')]
    public function testCheckIndicator(PackageInfo $packageInfo, SystemIndicator $indicator, bool|string $expected): void
    {
        $reflectionMethod = new ReflectionMethod(SystemValidator::class, 'checkIndicator');

        if (is_string($expected) && class_exists($expected)) {
            $this->expectException($expected);
            $reflectionMethod->invoke(null, $packageInfo, $indicator);
        } else {
            $this->assertEquals($expected, $reflectionMethod->invoke(null, $packageInfo, $indicator), 'Expected "' . $indicator->getType()->value . ':' . $indicator->getValue() . '" to be "' . ($expected ? 'true' : 'false') . '".');
        }
    }

    public static function providetestCheckIndicatorData(): Generator
    {
        $defaultPackageInfo = new PackageInfo(
            namespace: 'TestNamespace',
            projectPath: self::TEST_FILE_DIR,
            autoloadPath: self::TEST_FILE_DIR,
            packagePath: self::TEST_FILE_DIR,
            composerName: 'test/package',
        );

        yield [$defaultPackageInfo, new SystemIndicator(SystemIndicatorTypeEnum::DIRECTORY, '/existingDir'), true];
        yield [$defaultPackageInfo, new SystemIndicator(SystemIndicatorTypeEnum::DIRECTORY, '/nonExistingDir'), false];

        yield [$defaultPackageInfo, new SystemIndicator(SystemIndicatorTypeEnum::FILE, '/existingFile.txt'), true];
        yield [$defaultPackageInfo, new SystemIndicator(SystemIndicatorTypeEnum::FILE, '/nonExistingFile.txt'), false];
        yield [$defaultPackageInfo, new SystemIndicator(SystemIndicatorTypeEnum::FILE, '/existingDir/existingFile.txt'), true];
        yield [$defaultPackageInfo, new SystemIndicator(SystemIndicatorTypeEnum::FILE, '/nonExistingDir/nonExistingFile.txt'), false];
        yield [$defaultPackageInfo, new SystemIndicator(SystemIndicatorTypeEnum::FILE, '/existingDir/nonExistingFile.txt'), false];

        foreach (self::providetestCheckForEnvVariableData() as $data) {
            yield [$defaultPackageInfo, new SystemIndicator(SystemIndicatorTypeEnum::ENV, $data[0]), $data[1]];
        }
    }

}
