<?php

namespace App\Tests\Doctrine;

use App\Doctrine\EmailStatusType;
use App\ValueObject\EmailStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

class EmailStatusTypeTest extends TestCase
{
    private EmailStatusType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new EmailStatusType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testGetName(): void
    {
        $this->assertEquals('email_status', $this->type->getName());
        $this->assertEquals(EmailStatusType::EMAIL_STATUS, $this->type->getName());
    }

    public function testGetSQLDeclarationSetsLengthTo50(): void
    {
        $this->platform->expects($this->once())
            ->method('getStringTypeDeclarationSQL')
            ->with($this->callback(function (array $column) {
                return $column['length'] === 50;
            }))
            ->willReturn('VARCHAR(50)');

        $result = $this->type->getSQLDeclaration([], $this->platform);

        $this->assertEquals('VARCHAR(50)', $result);
    }

    public function testConvertToPHPValueReturnsNullForNull(): void
    {
        $result = $this->type->convertToPHPValue(null, $this->platform);

        $this->assertNull($result);
    }

    public function testConvertToPHPValueReturnsEmailStatusInstance(): void
    {
        $result = $this->type->convertToPHPValue('Versendet', $this->platform);

        $this->assertInstanceOf(EmailStatus::class, $result);
        $this->assertEquals('Versendet', $result->getValue());
    }

    public function testConvertToDatabaseValueReturnsNullForNull(): void
    {
        $result = $this->type->convertToDatabaseValue(null, $this->platform);

        $this->assertNull($result);
    }

    public function testConvertToDatabaseValueReturnsStringFromEmailStatus(): void
    {
        $status = EmailStatus::sent();

        $result = $this->type->convertToDatabaseValue($status, $this->platform);

        $this->assertEquals('Versendet', $result);
    }

    public function testConvertToDatabaseValueThrowsExceptionForWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Expected EmailStatus/');

        $this->type->convertToDatabaseValue(new \stdClass(), $this->platform);
    }

    public function testRequiresSQLCommentHintReturnsTrue(): void
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }

    public function testConvertToPHPValueWithErrorStatus(): void
    {
        $status = $this->type->convertToPHPValue('Fehler: smtp error', $this->platform);

        $this->assertInstanceOf(EmailStatus::class, $status);
        $this->assertTrue($status->isError());
    }

    public function testRoundtripConversion(): void
    {
        $original = EmailStatus::sent();
        $dbValue = $this->type->convertToDatabaseValue($original, $this->platform);
        $restored = $this->type->convertToPHPValue($dbValue, $this->platform);

        $this->assertInstanceOf(EmailStatus::class, $restored);
        $this->assertTrue($original->equals($restored));
    }
}
