<?php

namespace App\Tests\Doctrine\Type;

use App\Doctrine\Type\TicketIdType;
use App\ValueObject\TicketId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

class TicketIdTypeTest extends TestCase
{
    private TicketIdType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new TicketIdType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testGetName(): void
    {
        $this->assertEquals('ticket_id', $this->type->getName());
        $this->assertEquals(TicketIdType::NAME, $this->type->getName());
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

    public function testConvertToPHPValueReturnsTicketIdInstance(): void
    {
        $result = $this->type->convertToPHPValue('TICKET-123', $this->platform);

        $this->assertInstanceOf(TicketId::class, $result);
        $this->assertEquals('TICKET-123', $result->getValue());
    }

    public function testConvertToDatabaseValueReturnsNullForNull(): void
    {
        $result = $this->type->convertToDatabaseValue(null, $this->platform);

        $this->assertNull($result);
    }

    public function testConvertToDatabaseValueReturnsStringFromTicketId(): void
    {
        $ticketId = TicketId::fromString('TICKET-456');

        $result = $this->type->convertToDatabaseValue($ticketId, $this->platform);

        $this->assertEquals('TICKET-456', $result);
    }

    public function testConvertToDatabaseValueThrowsExceptionForWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected TicketId instance');

        $this->type->convertToDatabaseValue('not-a-ticket-id-object', $this->platform);
    }

    public function testRequiresSQLCommentHintReturnsTrue(): void
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}
