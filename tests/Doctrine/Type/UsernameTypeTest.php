<?php

namespace App\Tests\Doctrine\Type;

use App\Doctrine\Type\UsernameType;
use App\ValueObject\Username;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

class UsernameTypeTest extends TestCase
{
    private UsernameType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new UsernameType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testGetName(): void
    {
        $this->assertEquals('username', $this->type->getName());
        $this->assertEquals(UsernameType::NAME, $this->type->getName());
    }

    public function testGetSQLDeclaration(): void
    {
        $this->platform->expects($this->once())
            ->method('getStringTypeDeclarationSQL')
            ->with([])
            ->willReturn('VARCHAR(255)');

        $result = $this->type->getSQLDeclaration([], $this->platform);

        $this->assertEquals('VARCHAR(255)', $result);
    }

    public function testConvertToPHPValueReturnsNullForNull(): void
    {
        $result = $this->type->convertToPHPValue(null, $this->platform);

        $this->assertNull($result);
    }

    public function testConvertToPHPValueReturnsUsernameInstance(): void
    {
        $result = $this->type->convertToPHPValue('john.doe', $this->platform);

        $this->assertInstanceOf(Username::class, $result);
        $this->assertEquals('john.doe', $result->getValue());
    }

    public function testConvertToDatabaseValueReturnsNullForNull(): void
    {
        $result = $this->type->convertToDatabaseValue(null, $this->platform);

        $this->assertNull($result);
    }

    public function testConvertToDatabaseValueReturnsStringFromUsername(): void
    {
        $username = Username::fromString('john.doe');

        $result = $this->type->convertToDatabaseValue($username, $this->platform);

        $this->assertEquals('john.doe', $result);
    }

    public function testConvertToDatabaseValueThrowsExceptionForWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected Username instance');

        $this->type->convertToDatabaseValue('not-a-username-object', $this->platform);
    }

    public function testRequiresSQLCommentHintReturnsTrue(): void
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}
