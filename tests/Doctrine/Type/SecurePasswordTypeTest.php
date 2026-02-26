<?php

namespace App\Tests\Doctrine\Type;

use App\Doctrine\Type\SecurePasswordType;
use App\ValueObject\SecurePassword;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

class SecurePasswordTypeTest extends TestCase
{
    private SecurePasswordType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new SecurePasswordType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testGetName(): void
    {
        $this->assertEquals('secure_password', $this->type->getName());
        $this->assertEquals(SecurePasswordType::NAME, $this->type->getName());
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

    public function testConvertToPHPValueReturnsSecurePasswordInstanceFromHash(): void
    {
        // A valid bcrypt hash
        $hash = '$2y$12$somehashedvalue1234567890123456789012345678901234567890';

        $result = $this->type->convertToPHPValue($hash, $this->platform);

        $this->assertInstanceOf(SecurePassword::class, $result);
        $this->assertEquals($hash, $result->getHash());
    }

    public function testConvertToDatabaseValueReturnsNullForNull(): void
    {
        $result = $this->type->convertToDatabaseValue(null, $this->platform);

        $this->assertNull($result);
    }

    public function testConvertToDatabaseValueReturnsHashFromSecurePassword(): void
    {
        $hash = '$2y$12$somehashedvalue1234567890123456789012345678901234567890';
        $password = SecurePassword::fromHash($hash);

        $result = $this->type->convertToDatabaseValue($password, $this->platform);

        $this->assertEquals($hash, $result);
    }

    public function testConvertToDatabaseValueThrowsExceptionForWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected SecurePassword instance');

        $this->type->convertToDatabaseValue('not-a-password-object', $this->platform);
    }

    public function testRequiresSQLCommentHintReturnsTrue(): void
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}
