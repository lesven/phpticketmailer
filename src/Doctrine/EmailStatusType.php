<?php
declare(strict_types=1);
/**
 * EmailStatusType.php
 * 
 * Doctrine Type für das EmailStatus Value Object.
 * Ermöglicht die Verwendung von EmailStatus als Datenbank-Spaltentyp.
 * 
 * @package App\Doctrine
 */

namespace App\Doctrine;

use App\ValueObject\EmailStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type für EmailStatus Value Object
 */
final class EmailStatusType extends Type
{
    public const EMAIL_STATUS = 'email_status';

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] = 50;
        return $platform->getStringTypeDeclarationSQL($column);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?EmailStatus
    {
        if ($value === null) {
            return null;
        }

        return EmailStatus::fromString($value);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof EmailStatus) {
            throw new \InvalidArgumentException('Expected EmailStatus, got ' . $value::class);
        }

        return $value->getValue();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::EMAIL_STATUS;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
