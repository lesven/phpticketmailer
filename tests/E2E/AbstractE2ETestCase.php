<?php

namespace App\Tests\E2E;

use App\Entity\User;
use App\Entity\EmailSent;
use App\Entity\CsvFieldConfig;
use App\Entity\SMTPConfig;
use App\Repository\UserRepository;
use App\Repository\EmailSentRepository;
use App\Repository\CsvFieldConfigRepository;
use App\Repository\SMTPConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class AbstractE2ETestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;
    protected UserRepository $userRepository;
    protected EmailSentRepository $emailSentRepository;
    protected CsvFieldConfigRepository $csvFieldConfigRepository;
    protected SMTPConfigRepository $smtpConfigRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        
        try {
            $this->entityManager = static::getContainer()->get('doctrine')->getManager();
            $this->userRepository = static::getContainer()->get(UserRepository::class);
            $this->emailSentRepository = static::getContainer()->get(EmailSentRepository::class);
            $this->csvFieldConfigRepository = static::getContainer()->get(CsvFieldConfigRepository::class);
            $this->smtpConfigRepository = static::getContainer()->get(SMTPConfigRepository::class);
            
            $this->cleanDatabase();
            $this->setupBasicConfig();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for E2E tests: ' . $e->getMessage());
        }
    }

    protected function cleanDatabase(): void
    {
        // Clean in correct order due to foreign key constraints
        $this->entityManager->createQuery('DELETE FROM App\Entity\EmailSent')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\CsvFieldConfig')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\SMTPConfig')->execute();
        $this->entityManager->flush();
    }

    protected function setupBasicConfig(): void
    {
        // Setup CSV field configuration
        $csvConfig = new CsvFieldConfig();
        $csvConfig->setEmailField('email');
        $csvConfig->setTicketNumberField('ticket_number');
        $csvConfig->setCustomField('custom_field');
        $this->entityManager->persist($csvConfig);

        // Setup SMTP configuration for testing
        $smtpConfig = new SMTPConfig();
        $smtpConfig->setHost('localhost');
        $smtpConfig->setPort(1025); // MailHog default port
        $smtpConfig->setUsername('test');
        $smtpConfig->setPassword('test');
        $smtpConfig->setEncryption('none');
        $smtpConfig->setFromEmail('test@ticketmailer.local');
        $smtpConfig->setFromName('Ticket Mailer Test');
        $this->entityManager->persist($smtpConfig);

        $this->entityManager->flush();
    }

    protected function createTestCsvFile(string $content, string $filename = 'test.csv'): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'e2e_csv_');
        file_put_contents($tempFile, $content);
        
        return new UploadedFile(
            $tempFile,
            $filename,
            'text/csv',
            null,
            true
        );
    }

    protected function loadCsvFixture(string $fixtureName): UploadedFile
    {
        $fixturePath = __DIR__ . '/../fixtures/csv/' . $fixtureName;
        
        if (!file_exists($fixturePath)) {
            throw new \RuntimeException("CSV fixture not found: {$fixturePath}");
        }

        $content = file_get_contents($fixturePath);
        return $this->createTestCsvFile($content, $fixtureName);
    }

    protected function assertEmailsSentToUsers(array $expectedEmails): void
    {
        $emailsSent = $this->emailSentRepository->findAll();
        $sentToEmails = [];

        foreach ($emailsSent as $emailSent) {
            $this->assertNotNull($emailSent->getUser());
            $this->assertNotNull($emailSent->getSentAt());
            $this->assertNotEmpty($emailSent->getContent());
            
            $sentToEmails[] = $emailSent->getUser()->getEmail();
        }

        sort($expectedEmails);
        sort($sentToEmails);
        
        $this->assertEquals($expectedEmails, $sentToEmails, 'Emails should be sent to expected recipients');
    }

    protected function assertUsersImported(array $expectedData): void
    {
        $users = $this->userRepository->findAll();
        $this->assertCount(count($expectedData), $users, 'Should import expected number of users');

        foreach ($expectedData as $expected) {
            $user = $this->userRepository->findOneBy(['email' => $expected['email']]);
            $this->assertNotNull($user, "User with email {$expected['email']} should exist");
            $this->assertEquals($expected['ticket_number'], $user->getTicketNumber());
            
            if (isset($expected['custom_field'])) {
                $this->assertEquals($expected['custom_field'], $user->getCustomField());
            }
        }
    }

    protected function simulateLogin(): void
    {
        // For E2E tests, we might need to simulate admin login
        // This depends on your authentication system
        $this->client->request('POST', '/login', [
            'password' => 'test_admin_password' // Use test password
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }
}