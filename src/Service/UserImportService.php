<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\ValueObject\EmailAddress;
use App\Exception\InvalidEmailAddressException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service für den Import von Benutzern aus CSV-Dateien
 * 
 * Kapselt die komplette Logik für das Importieren, Validieren und 
 * Deduplizieren von Benutzerdaten aus CSV-Dateien.
 */
class UserImportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly CsvFileReader $csvFileReader,
        private readonly CsvValidationService $csvValidationService,
        private readonly UserValidator $userValidator,
        private readonly UserCsvHelper $csvHelper
    ) {
    }

    /**
     * Führt einen kompletten Benutzer-Import aus einer CSV-Datei durch
     * 
     * @param UploadedFile $csvFile Die CSV-Datei mit Benutzerdaten
     * @param bool $clearExisting Ob bestehende Benutzer vor dem Import gelöscht werden sollen
     * @return UserImportResult Ergebnis des Import-Vorgangs
     */    public function importUsersFromCsv(UploadedFile $csvFile, bool $clearExisting = false): UserImportResult
    {
        try {
            // 1. CSV-Datei öffnen und lesen
            $handle = $this->csvFileReader->openCsvFile($csvFile);
            $header = $this->csvFileReader->readHeader($handle);
            
            // 2. Header validieren (erwarten 'username' und 'email' Spalten)
            $requiredColumns = ['username', 'email'];
            $columnIndices = $this->csvFileReader->validateRequiredColumns($header, $requiredColumns);
            
            // 3. Alle Zeilen sammeln
            $userData = [];
            $this->csvFileReader->processRows($handle, function ($row, $rowNumber) use (&$userData, $columnIndices) {
                if (count($row) >= max($columnIndices) + 1) {
                    $userData[] = $this->csvHelper->mapRowToUserData($row, $columnIndices);
                }
            });
            
            // 4. Handle schließen
            $this->csvFileReader->closeHandle($handle);
            
            if (empty($userData)) {
                return UserImportResult::error('Die CSV-Datei ist leer oder enthält keine gültigen Daten.');
            }

            // 5. Datenvalidierung
            $validationResult = $this->validateCsvData($userData);
            if (!$validationResult['success']) {
                return UserImportResult::error($validationResult['message']);
            }            // 6. Bestehende Benutzer löschen falls gewünscht
            if ($clearExisting) {
                $this->clearExistingUsers();
            }

            // 7. Duplikate entfernen
            $uniqueData = $this->csvValidationService->removeDuplicates($userData, 'email');

            // 8. Benutzer erstellen und speichern
            $result = $this->createAndPersistUsers($uniqueData, $clearExisting);

            return $result;

        } catch (\Exception $e) {
            return UserImportResult::error('Fehler beim Importieren: ' . $e->getMessage());
        }
    }

    /**
     * Exportiert alle Benutzer in CSV-Format
     * 
     * @return string CSV-Inhalt als String
     */
    public function exportUsersToCsv(): string
    {
        $users = $this->userRepository->findAll();
        
        $csvContent = "ID,username,email\n";
        
        foreach ($users as $user) {
            $csvContent .= $this->csvHelper->formatUserAsCsvLine($user);
        }
        
        return $csvContent;
    }

    /**
     * Validiert die CSV-Daten für den Benutzer-Import
     */
    private function validateCsvData(array $csvData): array
    {
        $requiredFields = ['username', 'email'];
        $errors = [];

        foreach ($csvData as $index => $row) {
            $rowValidation = $this->csvValidationService->validateCsvRow($row, $requiredFields, $index + 1);
            
            if (!$rowValidation['valid']) {
                $errors = array_merge($errors, $rowValidation['errors']);
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Validierungsfehler: ' . implode(', ', array_slice($errors, 0, 5))
            ];
        }

        return ['success' => true];
    }

    /**
     * Formatiert einen User als CSV-Zeile.
     */
    private function formatUserAsCsvLine(User $user): string
    {
        return sprintf(
            "%d,%s,%s\n",
            $user->getId(),
            $this->escapeCsvField((string) $user->getUsername()),
            $this->escapeCsvField((string) $user->getEmail())
        );
    }

    /**
     * Mappt eine CSV-Zeile auf das interne Benutzer-Daten-Array.
     *
     * @param array $row Die CSV-Zeile
     * @param array $columnIndices Assoziatives Array mit Spaltenindizes
     * @return array Assoziatives Array mit 'username' und 'email'
     */

    /**
     * Löscht alle bestehenden Benutzer
     */
    private function clearExistingUsers(): void
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(User::class, 'u');
        $qb->getQuery()->execute();
    }

    /**
     * Erstellt und persistiert Benutzer aus den CSV-Daten
     */
    private function createAndPersistUsers(array $userData, bool $clearExisting): UserImportResult
    {
        $createdCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($userData as $row) {
            try {
                $username = trim($row['username']);
                $email = trim($row['email']);

                // Prüfen ob Benutzer bereits existiert (nur wenn nicht alle gelöscht wurden)
                if (!$clearExisting && $this->userRepository->findByUsername($username)) {
                    $skippedCount++;
                    continue;
                }

                // Validierung mit UserValidator
                if (!$this->userValidator->isValidUsername($username) || 
                    !$this->userValidator->isValidEmail($email)) {
                    $errors[] = "Ungültige Daten für Benutzer '{$username}'";
                    continue;
                }

                // Benutzer erstellen
                $user = new User();
                $user->setUsername($username);
                
                try {
                    $emailAddress = EmailAddress::fromString($email);
                    $user->setEmail($emailAddress);
                } catch (InvalidEmailAddressException $e) {
                    $errors[] = "Ungültige E-Mail für Benutzer '{$username}': " . $e->getMessage();
                    continue;
                }

                $this->entityManager->persist($user);
                $createdCount++;

            } catch (\Exception $e) {
                $errors[] = "Fehler beim Erstellen von Benutzer '{$row['username']}': " . $e->getMessage();
            }
        }

        // Alle Änderungen speichern
        if ($createdCount > 0) {
            $this->entityManager->flush();
        }

        return UserImportResult::success($createdCount, $skippedCount, $errors);
    }

    /**
     * Escaped ein CSV-Feld für den Export
     */
}
