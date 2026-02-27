<?php
declare(strict_types=1);

namespace App\Dto;

use App\ValueObject\EmailAddress;

/**
 * Typisiertes DTO für die E-Mail-Versandkonfiguration.
 *
 * Ersetzt das vorherige untypisierte assoziative Array und macht
 * die Konfiguration explizit, IDE-unterstützt und validiert.
 */
final readonly class EmailConfig
{
    public function __construct(
        public string $subject,
        public string $ticketBaseUrl,
        public EmailAddress|string $senderEmail,
        public string $senderName,
        public EmailAddress|string $testEmail,
        public bool $useCustomSMTP,
        public ?string $smtpDSN = null,
    ) {
    }
}
