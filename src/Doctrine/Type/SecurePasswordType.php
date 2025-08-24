<?php

namespace App\Doctrine\Type;

use App\ValueObject\SecurePassword;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class SecurePasswordType extends Type
{
    public const NAME = 'secure_password';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?SecurePassword
    {
        if ($value === null) {
            return null;
        }

        return SecurePassword::fromHash((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof SecurePassword) {
            throw new \InvalidArgumentException('Expected SecurePassword instance');
        }

        return $value->getHash();
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