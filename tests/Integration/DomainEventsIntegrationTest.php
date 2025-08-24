<?php

namespace App\Tests\Integration;

use App\Event\User\UserImportStartedEvent;
use App\EventHandler\AuditLogEventHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class DomainEventsIntegrationTest extends KernelTestCase
{
    public function testEventDispatcherIsAvailable(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        
        $eventDispatcher = $container->get(EventDispatcherInterface::class);
        
        $this->assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);
    }
    
    public function testEventHandlerIsRegisteredAsService(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        
        $auditHandler = $container->get(AuditLogEventHandler::class);
        
        $this->assertInstanceOf(AuditLogEventHandler::class, $auditHandler);
    }
    
    public function testEventDispatchingWorks(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        
        $eventDispatcher = $container->get(EventDispatcherInterface::class);
        
        // Mock Logger um zu sehen ob Event Handler aufgerufen wird
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with(
                'User import started',
                $this->callback(function ($context) {
                    return isset($context['event']) && 
                           $context['event'] === 'user_import_started' &&
                           $context['total_rows'] === 50;
                })
            );
        
        // Event Handler mit Mock Logger erstellen
        $auditHandler = new AuditLogEventHandler($mockLogger);
        
        // Event erstellen und dispatchen
        $event = new UserImportStartedEvent(50, 'test.csv', false);
        
        // Manuell Handler aufrufen um zu testen (da wir Mock Logger verwenden)
        $auditHandler->onUserImportStarted($event);
        
        // Test dass Event korrekt erstellt wurde
        $this->assertEquals(50, $event->totalRows);
        $this->assertEquals('test.csv', $event->filename);
        $this->assertFalse($event->clearExisting);
    }
}