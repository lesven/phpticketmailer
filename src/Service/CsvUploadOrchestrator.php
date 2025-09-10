<?php

namespace App\Service;

use App\Entity\CsvFieldConfig;
use App\Repository\CsvFieldConfigRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service zur Orchestrierung des gesamten CSV-Upload-Prozesses
 * 
 * Koordiniert alle Schritte von der Datei-Validierung bis zum E-Mail-Versand
 * und sorgt für eine klare Trennung der Verantwortlichkeiten.
 */
class CsvUploadOrchestrator
{
    public function __construct(
        private readonly CsvProcessor $csvProcessor,
        private readonly CsvFieldConfigRepository $csvFieldConfigRepository,
        private readonly SessionManager $sessionManager,
        private readonly UserCreator $userCreator
    ) {}

    /**
     * Verarbeitet einen kompletten CSV-Upload-Vorgang
     * 
     * @param UploadedFile $csvFile Die hochgeladene CSV-Datei
     * @param bool $testMode Ob E-Mails im Testmodus versendet werden sollen
     * @param bool $forceResend Ob bereits verarbeitete Tickets erneut versendet werden sollen
     * @param CsvFieldConfig $csvFieldConfig Konfiguration der CSV-Feldnamen
     * 
     * @return UploadResult Ergebnis der Verarbeitung mit Weiterleitung und Meldungen
     */
    public function processUpload(
        UploadedFile $csvFile,
        bool $testMode,
        bool $forceResend,
        CsvFieldConfig $csvFieldConfig
    ): UploadResult {
        // 1. CSV-Konfiguration speichern
        $this->csvFieldConfigRepository->saveConfig($csvFieldConfig);

        // 2. CSV-Datei verarbeiten
        $processingResult = $this->csvProcessor->process($csvFile, $csvFieldConfig);

        // 3. Ergebnisse in Session speichern
        $this->sessionManager->storeUploadResults($processingResult);

        // 4. Entscheidung über nächsten Schritt treffen
        if (!empty($processingResult['unknownUsers'])) {
            return UploadResult::redirectToUnknownUsers(
                $testMode,
                $forceResend,
                count($processingResult['unknownUsers'])
            );
        }

        return UploadResult::redirectToEmailSending($testMode, $forceResend);
    }

    /**
     * Verarbeitet unbekannte Benutzer und erstellt neue User-Entitäten
     * 
     * @param array $emailMappings Mapping von Benutzername zu E-Mail-Adresse
     * @return UnknownUsersResult Ergebnis der Verarbeitung
     */
    public function processUnknownUsers(array $emailMappings): UnknownUsersResult
    {
        $unknownUsers = $this->sessionManager->getUnknownUsers();

        if (empty($unknownUsers)) {
            return UnknownUsersResult::noUsersFound();
        }

        $newUsersCount = $this->createUsersFromMappings($emailMappings, $unknownUsers);

        return UnknownUsersResult::success($newUsersCount);
    }

    /**
     * Erstellt neue User-Entitäten aus den E-Mail-Zuordnungen
     *
     * Diese private Methode iteriert über alle unbekannten Benutzer und erstellt
     * für jeden, der eine E-Mail-Zuordnung hat, eine neue User-Entität.
     * Anschließend werden alle erstellten Benutzer persistiert.
     *
     * @param array $emailMappings Assoziatives Array: Benutzername => E-Mail-Adresse
     * @param array $unknownUsers Liste der unbekannten Benutzernamen
     * @return int Anzahl der erfolgreich erstellten Benutzer
     */
    private function createUsersFromMappings(array $emailMappings, array $unknownUsers): int
    {
        foreach ($unknownUsers as $username) {
            if (isset($emailMappings[$username])) {
                try {
                    $this->userCreator->createUser($username, $emailMappings[$username]);
                } catch (\InvalidArgumentException $e) {
                    // Log ungültige Benutzernamen, aber lass den Prozess weiterlaufen
                    error_log("Invalid username skipped in createUsersFromMappings: '{$username}' - " . $e->getMessage());
                }
            }
        }

        return $this->userCreator->persistUsers();
    }
}
