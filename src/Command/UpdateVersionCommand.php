<?php

namespace App\Command;

use App\Service\VersionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Befehl zur Aktualisierung der Versionsinformationen
 */
#[AsCommand(
    name: 'app:update-version',
    description: 'Aktualisiert die Versionsinformationen der Anwendung',
)]
class UpdateVersionCommand extends Command
{
    private $versionService;
    
    /**
     * Konstruktor
     *
     * @param VersionService $versionService
     */
    public function __construct(VersionService $versionService)
    {
        parent::__construct();
        $this->versionService = $versionService;
    }
    
    /**
     * Konfiguration des Befehls
     */
    protected function configure(): void
    {
        $this
            ->addOption('version', 'v', InputOption::VALUE_OPTIONAL, 'Neue Versionsnummer')
            ->addOption('no-timestamp', null, InputOption::VALUE_NONE, 'Zeitstempel nicht aktualisieren');
    }
    
    /**
     * Ausführung des Befehls
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
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
