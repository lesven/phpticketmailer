<?php

namespace App\Tests\Doctrine\Type;

use App\Doctrine\Type\TicketNameType;
use App\ValueObject\TicketName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

class TicketNameTypeTest extends TestCase
{
    private TicketNameType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new TicketNameType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testGetName(): void
    {
        $this->assertEquals('ticket_name', $this->type->getName());
        $this->assertEquals(TicketNameType::NAME, $this->type->getName());
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

    public function testConvertToPHPValueReturnsTicketNameInstance(): void
    {
        $result = $this->type->convertToPHPValue('System Issue', $this->platform);

        $this->assertInstanceOf(TicketName::class, $result);
        $this->assertEquals('System Issue', $result->getValue());
    }

    public function testConvertToDatabaseValueReturnsNullForNull(): void
    {
        $result = $this->type->convertToDatabaseValue(null, $this->platform);

        $this->assertNull($result);
    }

    public function testConvertToDatabaseValueReturnsStringFromTicketName(): void
    {
        $ticketName = TicketName::fromString('System Issue');

        $result = $this->type->convertToDatabaseValue($ticketName, $this->platform);

        $this->assertEquals('System Issue', $result);
    }

    public function testConvertToDatabaseValueThrowsExceptionForWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected TicketName instance');

        $this->type->convertToDatabaseValue('not-a-ticket-name-object', $this->platform);
    }

    public function testRequiresSQLCommentHintReturnsTrue(): void
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}
