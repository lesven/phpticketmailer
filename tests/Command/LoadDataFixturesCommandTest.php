<?php

namespace App\Tests\Command;

use App\Command\LoadDataFixturesCommand;
use App\Entity\User;
use App\Entity\EmailSent;
use App\Entity\SMTPConfig;
use App\Entity\CsvFieldConfig;
use App\Entity\AdminPassword;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class LoadDataFixturesCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private LoadDataFixturesCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->command = new LoadDataFixturesCommand($this->entityManager);
        
        $application = new Application();
        $application->add($this->command);
        
        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandIsCorrectlyConfigured(): void
    {
        $this->assertSame('app:load-data-fixtures', $this->command->getName());
        $this->assertStringContainsString('Lädt Testdaten', $this->command->getDescription());
        
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('force'));
    }

    public function testCommandWithoutForceShowsWarningWhenFixturesExist(): void
    {
        // Mock the check for existing fixtures to return true
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getSingleScalarResult')->willReturn(1); // Fixtures exist
        
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);
        
        $this->entityManager->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Fixture-Daten existieren bereits', $output);
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testCommandCreatesExpectedEntities(): void
    {
        // Mock the check for existing fixtures to return false
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getSingleScalarResult')->willReturn(0); // No fixtures exist
        
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);
        
        $this->entityManager->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        // Track what entities are persisted
        $persistedEntities = [];
        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function($entity) use (&$persistedEntities) {
                $persistedEntities[] = $entity;
            });

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->commandTester->execute([]);

        // Verify different entity types were created
        $entityTypes = array_map('get_class', $persistedEntities);
        $this->assertContains(User::class, $entityTypes);
        $this->assertContains(SMTPConfig::class, $entityTypes);
        $this->assertContains(CsvFieldConfig::class, $entityTypes);
        $this->assertContains(EmailSent::class, $entityTypes);
        $this->assertContains(AdminPassword::class, $entityTypes);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Alle Fixture-Daten wurden erfolgreich geladen', $output);
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testFixtureDataContent(): void
    {
        // Test that fixture data has expected content
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getSingleScalarResult')->willReturn(0);
        
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);
        
        $this->entityManager->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        $persistedEntities = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function($entity) use (&$persistedEntities) {
                $persistedEntities[] = $entity;
            });

        $this->commandTester->execute([]);

        // Check User entities
        $users = array_filter($persistedEntities, fn($e) => $e instanceof User);
        $this->assertCount(12, $users);
        
        foreach ($users as $user) {
            // All fixture usernames must start with the common prefix 'fixtures_'
            $this->assertStringStartsWith('fixtures_', $user->getUsername());
            $this->assertStringContainsString('@example.com', $user->getEmail());
        }

        // Check EmailSent entities
        $emails = array_filter($persistedEntities, fn($e) => $e instanceof EmailSent);
        $this->assertCount(15, $emails);
        
        foreach ($emails as $email) {
            $this->assertStringStartsWith('FIXTURE-', $email->getTicketId());
        }

        // Check SMTPConfig
        $smtpConfigs = array_filter($persistedEntities, fn($e) => $e instanceof SMTPConfig);
        $this->assertCount(1, $smtpConfigs);
        
        $smtpConfig = reset($smtpConfigs);
        $this->assertEquals('mailpit', $smtpConfig->getHost());
        $this->assertEquals(1025, $smtpConfig->getPort());

        // Check AdminPassword
        $adminPasswords = array_filter($persistedEntities, fn($e) => $e instanceof AdminPassword);
        $this->assertCount(1, $adminPasswords);
        
        $adminPassword = reset($adminPasswords);
        $this->assertNotNull($adminPassword->getPassword());
        $this->assertTrue(password_verify('admin123', $adminPassword->getPassword()));
    }
}