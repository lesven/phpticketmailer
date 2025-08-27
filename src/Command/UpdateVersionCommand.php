<?php
/**
 * UpdateVersionCommand.php
 *
 * Dieser Symfony Console Command stellt einen Befehl zur Verfügung, um die
 * Versionsinformationen der Anwendung zu aktualisieren. Er kann verwendet werden,
 * um eine neue Versionsnummer zu setzen und den Update-Zeitstempel zu aktualisieren.
 *
 * @package App\Command
 */

namespace App\Command;

use App\Service\VersionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console Command zur Aktualisierung der Versionsinformationen
 *
 * Dieser Command ermöglicht es, die Versionsinformationen der Anwendung
 * über die Kommandozeile zu aktualisieren. Optional kann eine spezifische
 * Versionsnummer angegeben werden.
 */
#[AsCommand(
    name: 'app:update-version',
    description: 'Aktualisiert die Versionsinformationen der Anwendung',
)]
class UpdateVersionCommand extends Command
{
    /**
     * Service für Versionsverwaltung
     */
    private VersionService $versionService;
    
    /**
     * Konstruktor
     *
     * @param VersionService $versionService Service für die Versionsverwaltung
     */
    public function __construct(VersionService $versionService)
    {
        parent::__construct();
        $this->versionService = $versionService;
    }
    
    /**
     * Konfiguriert die verfügbaren Optionen des Commands
     *
     * Definiert die CLI-Optionen:
     * - --version/-v: Optionale neue Versionsnummer
     * - --no-timestamp: Verhindert die Aktualisierung des Zeitstempels
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption('version', 'v', InputOption::VALUE_OPTIONAL, 'Neue Versionsnummer')
            ->addOption('no-timestamp', null, InputOption::VALUE_NONE, 'Zeitstempel nicht aktualisieren');
    }
    
    /**
     * Führt die Aktualisierung der Versionsinformationen aus
     *
     * Diese Methode orchestriert den Aktualisierungsprozess:
     * 1. Liest die übergebenen Optionen
     * 2. Delegiert die Aktualisierung an den VersionService
     * 3. Zeigt das Ergebnis in einer formatierten Tabelle an
     *
     * @param InputInterface $input Die Eingabe-Parameter
     * @param OutputInterface $output Die Ausgabe-Schnittstelle
     * @return int Command::SUCCESS bei Erfolg, Command::FAILURE bei Fehlern
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $version = $input->getOption('version');
        $noTimestamp = $input->getOption('no-timestamp');
        
        $result = $this->versionService->updateVersionInfo($version, !$noTimestamp);
        
        if ($result) {
            $io->success('Versionsinformationen wurden erfolgreich aktualisiert.');
            $io->table(
                ['Version', 'Update-Zeitstempel'],
                [[$this->versionService->getVersion(), $this->versionService->getUpdateTimestamp()]]
            );
            return Command::SUCCESS;
        } else {
            $io->error('Fehler beim Aktualisieren der Versionsinformationen.');
            return Command::FAILURE;
        }
    }
}
