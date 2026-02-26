<?php

namespace App\Tests\Doctrine\Type;

use App\Doctrine\Type\EmailAddressType;
use App\ValueObject\EmailAddress;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

class EmailAddressTypeTest extends TestCase
{
    private EmailAddressType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new EmailAddressType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testGetName(): void
    {
        $this->assertEquals('email_address', $this->type->getName());
        $this->assertEquals(EmailAddressType::NAME, $this->type->getName());
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

    public function testConvertToPHPValueReturnsEmailAddressInstance(): void
    {
        $result = $this->type->convertToPHPValue('test@example.com', $this->platform);

        $this->assertInstanceOf(EmailAddress::class, $result);
        $this->assertEquals('test@example.com', $result->getValue());
    }

    public function testConvertToPHPValueNormalizesEmail(): void
    {
        $result = $this->type->convertToPHPValue('USER@Example.COM', $this->platform);

        $this->assertInstanceOf(EmailAddress::class, $result);
        $this->assertEquals('user@example.com', $result->getValue());
    }

    public function testConvertToDatabaseValueReturnsNullForNull(): void
    {
        $result = $this->type->convertToDatabaseValue(null, $this->platform);

        $this->assertNull($result);
    }

    public function testConvertToDatabaseValueReturnsStringFromEmailAddress(): void
    {
        $email = EmailAddress::fromString('test@example.com');

        $result = $this->type->convertToDatabaseValue($email, $this->platform);

        $this->assertEquals('test@example.com', $result);
    }

    public function testConvertToDatabaseValueThrowsExceptionForWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected EmailAddress instance');

        $this->type->convertToDatabaseValue('not-an-email-object', $this->platform);
    }

    public function testRequiresSQLCommentHintReturnsTrue(): void
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}
