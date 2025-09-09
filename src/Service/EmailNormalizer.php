<?php

namespace App\Service;

use App\Exception\InvalidEmailAddressException;
use App\ValueObject\EmailAddress;

/**
 * Service für die Normalisierung von E-Mail-Adressen
 * 
 * Unterstützt die Konvertierung von Outlook-Format "Nachname, Vorname <email@domain.de>"
 * zu normalen E-Mail-Adressen "email@domain.de"
 */
class EmailNormalizer
{
    /**
     * Normalisiert eine E-Mail-Adresse von verschiedenen Formaten zu einer Standard-E-Mail
     * 
     * Unterstützte Formate:
     * - Standard: email@domain.de
     * - Outlook: "Nachname, Vorname <email@domain.de>"
     * - Varianten: Name <email@domain.de>, "Name" <email@domain.de>
     * 
     * @param string $emailInput Die zu normalisierende E-Mail-Eingabe
     * @return string Die normalisierte E-Mail-Adresse
     * @throws \InvalidArgumentException Wenn keine gültige E-Mail extrahiert werden kann
     */
    public function normalizeEmail(string $emailInput): string
    {
        $emailInput = trim($emailInput);
        
        if (empty($emailInput)) {
            throw new \InvalidArgumentException('E-Mail-Eingabe darf nicht leer sein');
        }
        
        // Outlook-Format erkennen: "Nachname, Vorname <email@domain.de>" oder Varianten
        if (preg_match('/<([^<>]+)>/', $emailInput, $matches)) {
            $extractedEmail = trim($matches[1]);
            
            // Validieren der extrahierten E-Mail mit EmailAddress VO
            try {
                $emailAddress = EmailAddress::fromString($extractedEmail);
                return $emailAddress->getValue();
            } catch (InvalidEmailAddressException $e) {
                throw new \InvalidArgumentException("Ungültige E-Mail-Adresse in spitzen Klammern gefunden: {$extractedEmail}");
            }
        }
        
        // Standard-E-Mail-Format prüfen mit EmailAddress VO
        try {
            $emailAddress = EmailAddress::fromString($emailInput);
            return $emailAddress->getValue();
        } catch (InvalidEmailAddressException $e) {
            throw new \InvalidArgumentException("Ungültiges E-Mail-Format: {$emailInput}");
        }
    }
    
    /**
     * Überprüft, ob die Eingabe einem Outlook-Format entspricht
     * 
     * @param string $emailInput Die zu prüfende E-Mail-Eingabe
     * @return bool True, wenn es sich um ein Outlook-Format handelt
     */
    public function isOutlookFormat(string $emailInput): bool
    {
        return preg_match('/<[^<>]+>/', trim($emailInput)) === 1;
    }
    
    /**
     * Überprüft, ob die Eingabe einem Standard-E-Mail-Format entspricht
     * 
     * @param string $emailInput Die zu prüfende E-Mail-Eingabe
     * @return bool True, wenn es sich um ein Standard-E-Mail-Format handelt
     */
    public function isStandardEmailFormat(string $emailInput): bool
    {
        try {
            EmailAddress::fromString(trim($emailInput));
            return true;
        } catch (InvalidEmailAddressException $e) {
            return false;
        }
    }
    
    /**
     * Validiert, ob eine E-Mail-Eingabe in einem der unterstützten Formate vorliegt
     * 
     * @param string $emailInput Die zu validierende E-Mail-Eingabe
     * @return bool True, wenn das Format unterstützt wird
     */
    public function isValidEmailInput(string $emailInput): bool
    {
        try {
            $this->normalizeEmail($emailInput);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
