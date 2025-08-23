<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\EmailSent;
use App\Entity\SMTPConfig;
use App\Entity\CsvFieldConfig;
use App\Entity\AdminPassword;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
#[AsCommand(
    name: 'app:load-data-fixtures',
    description: 'Lädt Testdaten in die Datenbank für einfache Anwendungstests',
)]
class LoadDataFixturesCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Überschreibt existierende Fixture-Daten'
        );
        
        $this->setHelp(
            'Dieser Befehl lädt Testdaten in die Datenbank, um die Anwendung einfach testen zu können. ' .
            'Die Fixtures enthalten Beispielbenutzer, E-Mail-Konfiguration, CSV-Feldkonfiguration und ' .
            'Beispiel-E-Mail-Protokolle mit verschiedenen Status.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $io->title('Data Fixtures für Ticketumfrage-Tool');

        try {
            // Check if fixtures already exist
            if (!$force && $this->fixturesExist()) {
                $io->warning('Fixture-Daten existieren bereits. Verwenden Sie --force zum Überschreiben.');
                return Command::SUCCESS;
            }

            if ($force) {
                $io->section('Lösche existierende Fixture-Daten...');
                $this->clearExistingFixtures();
                $io->success('Existierende Daten gelöscht.');
            }

            $io->section('Lade Fixture-Daten...');
            
            $this->loadUserFixtures($io);
            $this->loadSMTPConfigFixtures($io);
            $this->loadCsvFieldConfigFixtures($io);
            $this->loadEmailSentFixtures($io);
            $this->loadAdminPasswordFixtures($io);

            $this->entityManager->flush();

            $io->success('Alle Fixture-Daten wurden erfolgreich geladen!');
            $io->note([
                'Erstellt:',
                '- 12 Testbenutzer (10 reguläre + 2 Sonderfälle)',
                '- 1 SMTP-Konfiguration für Mailpit',
                '- 1 CSV-Feldkonfiguration mit Standardwerten',
                '- 15 Beispiel-E-Mail-Protokolle mit verschiedenen Status',
                '- 1 Admin-Passwort (Benutzer: admin, Passwort: admin123)',
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Fehler beim Laden der Fixtures: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function fixturesExist(): bool
    {
        $userCount = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.username LIKE :pattern')
            ->setParameter('pattern', 'fixtures_%')
            ->getQuery()
            ->getSingleScalarResult();

        return $userCount > 0;
    }

    private function clearExistingFixtures(): void
    {
        // Delete fixture users
        $this->entityManager->createQueryBuilder()
            ->delete(User::class, 'u')
            ->where('u.username LIKE :pattern')
            ->setParameter('pattern', 'fixtures_%')
            ->getQuery()
            ->execute();

        // Delete fixture email logs
        $this->entityManager->createQueryBuilder()
            ->delete(EmailSent::class, 'e')
            ->where('e.ticketId LIKE :pattern')
            ->setParameter('pattern', 'FIXTURE-%')
            ->getQuery()
            ->execute();

        // Note: We don't delete SMTP config and CSV config as they might be needed
        // Delete only if they have fixture-specific values
        $smtpConfig = $this->entityManager->getRepository(SMTPConfig::class)->findOneBy(['host' => 'mailpit']);
        if ($smtpConfig) {
            $this->entityManager->remove($smtpConfig);
        }

        $csvConfig = $this->entityManager->getRepository(CsvFieldConfig::class)->findOneBy([]);
        if ($csvConfig && $csvConfig->getTicketIdField() === 'Vorgangsschlüssel') {
            // Only remove if it's the default fixture config
            $this->entityManager->remove($csvConfig);
        }
    }

    private function loadUserFixtures(SymfonyStyle $io): void
    {
        $io->progressStart(12);
        
        // Create 10 regular test users
        for ($i = 1; $i <= 10; $i++) {
            $user = new User();
            $user->setUsername("fixtures_user{$i}");
            $user->setEmail("user{$i}@example.com");
            
            // Make some users excluded from surveys for testing
            if ($i % 3 === 0) {
                $user->setExcludedFromSurveys(true);
            }
            
            $this->entityManager->persist($user);
            $io->progressAdvance();
        }
        
        // Add some edge case users for testing
        $edgeCaseUsers = [
            ['fixtures_admin_user', 'admin@example.com', true],
            ['fixtures_test.with.dots', 'test.dots@example.com', false],
        ];
        
        foreach ($edgeCaseUsers as $userData) {
            $user = new User();
            $user->setUsername($userData[0]);
            $user->setEmail($userData[1]);
            $user->setExcludedFromSurveys($userData[2]);
            
            $this->entityManager->persist($user);
            $io->progressAdvance();
        }
        
        $io->progressFinish();
        $io->writeln(' 12 Testbenutzer erstellt (10 reguläre + 2 Sonderfälle)');
    }

    private function loadSMTPConfigFixtures(SymfonyStyle $io): void
    {
        $smtpConfig = new SMTPConfig();
        $smtpConfig->setHost('mailpit');
        $smtpConfig->setPort(1025);
        $smtpConfig->setUsername(null);
        $smtpConfig->setPassword(null);
        $smtpConfig->setUseTLS(false);
        $smtpConfig->setVerifySSL(false);
        $smtpConfig->setSenderEmail('noreply@ticketmailer.local');
        $smtpConfig->setSenderName('Ticket Survey System');
        $smtpConfig->setTicketBaseUrl('https://tickets.example.com');

        $this->entityManager->persist($smtpConfig);
        $io->writeln('SMTP-Konfiguration für Mailpit erstellt');
    }

    private function loadCsvFieldConfigFixtures(SymfonyStyle $io): void
    {
        $csvConfig = new CsvFieldConfig();
        $csvConfig->setTicketIdField('Vorgangsschlüssel');
        $csvConfig->setUsernameField('Autor');
        $csvConfig->setTicketNameField('Zusammenfassung');

        $this->entityManager->persist($csvConfig);
        $io->writeln('CSV-Feldkonfiguration erstellt');
    }

    private function loadEmailSentFixtures(SymfonyStyle $io): void
    {
        $io->progressStart(15);
        
        $statuses = ['sent', 'error: SMTP connection failed', 'error: Invalid email', 'sent', 'sent'];
        $testModes = [true, false, true, false, false];
        
        for ($i = 1; $i <= 15; $i++) {
            $emailSent = new EmailSent();
            $emailSent->setTicketId(sprintf("FIXTURE-%03d", $i));
            $emailSent->setUsername("fixtures_user" . (($i % 10) + 1));
            $emailSent->setEmail("user" . (($i % 10) + 1) . "@example.com");
            $emailSent->setSubject(sprintf("Ticket-Umfrage: FIXTURE-%03d", $i));
            $emailSent->setStatus($statuses[$i % count($statuses)]);
            $emailSent->setTestMode($testModes[$i % count($testModes)]);
            $emailSent->setTicketName("Beispiel-Ticket #{$i}");
            
            // Create timestamps spread over the last 30 days
            $daysAgo = 30 - ($i * 2);
            $timestamp = new \DateTimeImmutable("-{$daysAgo} days");
            $emailSent->setTimestamp($timestamp);
            
            $this->entityManager->persist($emailSent);
            $io->progressAdvance();
        }
        
        $io->progressFinish();
        $io->writeln(' 15 E-Mail-Protokolle erstellt');
    }

    private function loadAdminPasswordFixtures(SymfonyStyle $io): void
    {
        $adminPassword = new AdminPassword();
        $adminPassword->setPlainPassword('admin123');
        
        // Hash the password using PHP's password_hash like in SecurityController
        $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
        $adminPassword->setPassword($hashedPassword);
        
        $this->entityManager->persist($adminPassword);
        $io->writeln('Admin-Passwort erstellt (admin123)');
    }
}