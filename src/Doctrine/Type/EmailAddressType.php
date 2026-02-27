<?php
declare(strict_types=1);

namespace App\Doctrine\Type;

use App\ValueObject\EmailAddress;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class EmailAddressType extends Type
{
    public const NAME = 'email_address';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?EmailAddress
    {
        if ($value === null) {
            return null;
        }

        return EmailAddress::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof EmailAddress) {
            throw new \InvalidArgumentException('Expected EmailAddress instance');
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