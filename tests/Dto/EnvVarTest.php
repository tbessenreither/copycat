<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Tests\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use Tbessenreither\Copycat\Dto\EnvVar;
use Tbessenreither\Copycat\Tests\TestCase;

#[CoversClass(EnvVar::class)]
class EnvVarTest extends TestCase
{
    public function testSetterAndGetter(): void
    {
        $envVar = new EnvVar(name: 'TEST_VAR', value: 'test value', description: 'a test variable', isFlag: true);

        $this->assertEquals('TEST_VAR', $envVar->getName());
        $this->assertEquals('test value', $envVar->getValue());
        $this->assertEquals('test value', $envVar->getValueAsString());
        $this->assertTrue($envVar->isFlag());
        $this->assertEquals('TEST_VAR="" # Flag # a test variable', (string) $envVar);


        $envVar2 = new EnvVar(name: 'ANOTHER_VAR', value: 'another value');
        $this->assertEquals('ANOTHER_VAR', $envVar2->getName());
        $this->assertEquals('another value', $envVar2->getValue());
        $this->assertEquals('another value', $envVar2->getValueAsString());
        $this->assertFalse($envVar2->isFlag());
        $this->assertEquals('ANOTHER_VAR="another value"', (string) $envVar2);

        $envVar3 = new EnvVar(name: 'BOOL_VAR', value: true);
        $this->assertEquals('BOOL_VAR', $envVar3->getName());
        $this->assertTrue($envVar3->getValue());
        $this->assertEquals('true', $envVar3->getValueAsString());
        $this->assertEquals('BOOL_VAR=true', (string) $envVar3);

        $envVar4 = new EnvVar(name: 'NULL_VAR', value: null);
        $this->assertEquals('NULL_VAR', $envVar4->getName());
        $this->assertNull($envVar4->getValue());
        $this->assertEquals('null', $envVar4->getValueAsString());
        $this->assertEquals('NULL_VAR=null', (string) $envVar4);
    }
}
