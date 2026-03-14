<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use Tbessenreither\Copycat\Dto\PackageInfo;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(PackageInfo::class)]
class PackageInfoTest extends TestCase
{
    public function testSetterAndGetter(): void
    {
        $packageInfo = new PackageInfo(
            namespace: 'My\\Test\\Namespace',
            projectPath: '/path/to/project',
            autoloadPath: '/path/to/autoload',
            packagePath: '/path/to/package',
            composerName: 'my/test-package',
        );

        $this->assertEquals('My\\Test\\Namespace', $packageInfo->getNamespace());
        $this->assertEquals('/path/to/project', $packageInfo->getProjectPath());
        $this->assertEquals('/path/to/autoload', $packageInfo->getAutoloadPath());
        $this->assertEquals('/path/to/package', $packageInfo->getPackagePath());
        $this->assertEquals('my/test-package', $packageInfo->getComposerName());

        // Test that trailing slashes are removed
        $packageInfoWithSlashes = new PackageInfo(
            namespace: 'My\\Test\\Namespace\\',
            projectPath: '/path/to/project/',
            autoloadPath: '/path/to/autoload/',
            packagePath: '/path/to/package/',
            composerName: 'my/test-package',
        );
        $this->assertEquals('My\\Test\\Namespace', $packageInfoWithSlashes->getNamespace());
        $this->assertEquals('/path/to/project', $packageInfoWithSlashes->getProjectPath());
        $this->assertEquals('/path/to/autoload', $packageInfoWithSlashes->getAutoloadPath());
        $this->assertEquals('/path/to/package', $packageInfoWithSlashes->getPackagePath());
        $this->assertEquals('my/test-package', $packageInfoWithSlashes->getComposerName());
    }
}
