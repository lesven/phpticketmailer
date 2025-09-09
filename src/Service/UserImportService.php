<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\ValueObject\EmailAddress;
use App\ValueObject\Username;
use App\Exception\InvalidEmailAddressException;
use App\Exception\InvalidUsernameException;
use App\Event\User\UserImportStartedEvent;
use App\Event\User\UserImportedEvent;
use App\Event\User\UserImportCompletedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        private readonly UserCsvHelper $csvHelper,
        private readonly EventDispatcherInterface $eventDispatcher
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
        $startTime = microtime(true);
        
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

            // 🔥 EVENT: Import gestartet
            $this->eventDispatcher->dispatch(new UserImportStartedEvent(
                count($userData),
                $csvFile->getClientOriginalName() ?? 'unknown.csv',
                $clearExisting
            ));

            // 5. Datenvalidierung
            $validationResult = $this->validateCsvData($userData);
            if (!$validationResult['success']) {
                return UserImportResult::error($validationResult['message']);
            }
            
            // 6. Bestehende Benutzer löschen falls gewünscht
            if ($clearExisting) {
                $this->clearExistingUsers();
            }

            // 7. Duplikate entfernen
            $uniqueData = $this->csvValidationService->removeDuplicates($userData, 'email');

            // 8. Benutzer erstellen und speichern
            $result = $this->createAndPersistUsers($uniqueData, $clearExisting);

            // 🔥 EVENT: Import abgeschlossen
            $endTime = microtime(true);
            $this->eventDispatcher->dispatch(new UserImportCompletedEvent(
                $result->createdCount,
                count($result->errors),
                $result->errors,
                $csvFile->getClientOriginalName() ?? 'unknown.csv',
                $endTime - $startTime
            ));

            return $result;

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $this->eventDispatcher->dispatch(new UserImportCompletedEvent(
                0,
                1,
                [$e->getMessage()],
                $csvFile->getClientOriginalName() ?? 'unknown.csv',
                $endTime - $startTime
            ));
            
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
     * Formatiert einen User als CSV-Zeile (delegiert an UserCsvHelper)
     *
     * @deprecated Diese Methode sollte nicht mehr verwendet werden.
     *             Nutzen Sie stattdessen UserCsvHelper::formatUserAsCsvLine()
     * @param User $user Die zu formatierende User-Entität
     * @return string Die formatierte CSV-Zeile
     */
    private function formatUserAsCsvLine(User $user): string
    {
        return $this->csvHelper->formatUserAsCsvLine($user);
    }

    /**
     * Löscht alle bestehenden Benutzer aus der Datenbank
     *
     * Diese Methode sollte mit Vorsicht verwendet werden, da sie alle
     * User-Entitäten unwiderruflich aus der Datenbank entfernt.
     *
     * @return void
     */
    private function clearExistingUsers(): void
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(User::class, 'u');
        $qb->getQuery()->execute();
    }

    /**
     * Erstellt und persistiert Benutzer aus den CSV-Daten
     *
     * Diese Methode verarbeitet die validierten CSV-Daten und erstellt
     * neue User-Entitäten. Bereits existierende Benutzer werden übersprungen.
     *
     * @param array $userData Die zu verarbeitenden Benutzerdaten
     * @param bool $clearExisting Ob bestehende Benutzer gelöscht wurden
     * @return UserImportResult Das Ergebnis des Import-Vorgangs
     */
    private function createAndPersistUsers(array $userData, bool $clearExisting): UserImportResult
    {
        $createdCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($userData as $row) {
            try {
                // 🎯 DDD: Value Objects für Domain-Validierung verwenden
                $username = Username::fromString($row['username']);
                $emailAddress = EmailAddress::fromString($row['email']);

                // Prüfen ob Benutzer bereits existiert (nur wenn nicht alle gelöscht wurden)
                if (!$clearExisting && $this->userRepository->findByUsername($username->getValue())) {
                    $skippedCount++;
                    continue;
                }

                // 🎯 DDD: Validierung bereits durch Value Objects erfolgt - kein UserValidator mehr nötig!

                // Benutzer erstellen
                $user = new User();
                $user->setUsername($username->getValue());
                $user->setEmail($emailAddress);

                $this->entityManager->persist($user);
                $createdCount++;

                // 🔥 EVENT: User wurde importiert
                $this->eventDispatcher->dispatch(new UserImportedEvent(
                    $user->getUsername(),
                    $user->getEmail(),
                    $user->isExcludedFromSurveys()
                ));

            } catch (InvalidUsernameException $e) {
                $errors[] = "Ungültiger Username '{$row['username']}': " . $e->getMessage();
            } catch (InvalidEmailAddressException $e) {
                $errors[] = "Ungültige E-Mail '{$row['email']}': " . $e->getMessage();
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
}
