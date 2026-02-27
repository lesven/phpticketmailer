<?php
declare(strict_types=1);

namespace App\Doctrine\Type;

use App\ValueObject\TicketName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TicketNameType extends Type
{
    public const NAME = 'ticket_name';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TicketName
    {
        if ($value === null) {
            return null;
        }
        return TicketName::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof TicketName) {
            throw new \InvalidArgumentException('Expected TicketName instance');
        }
        return $value->getValue();
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
