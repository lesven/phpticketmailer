<?php

namespace App\Tests\Service;

use App\Service\ErrorHandlingService;
use App\Exception\TicketMailerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use PHPUnit\Framework\TestCase;
use Doctrine\DBAL\Exception as DoctrineException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Mailer\Exception\TransportException;

class ErrorHandlingServiceTest extends TestCase
{
    private ErrorHandlingService $errorHandlingService;
    private LoggerInterface $logger;
    private FlashBagInterface $flashBag;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->flashBag = $this->createMock(FlashBagInterface::class);
        $this->requestStack = new RequestStack();

        // Create a session mock that returns our flashBag mock
        $session = $this->createMock(FlashBagAwareSessionInterface::class);
        $session->method('getFlashBag')->willReturn($this->flashBag);

        $request = new Request();
        $request->setSession($session);
        $this->requestStack->push($request);

        $this->errorHandlingService = new ErrorHandlingService($this->logger, $this->requestStack);
    }

    public function testHandleTicketMailerExceptionLogsErrorAndAddsFlashMessage(): void
    {
        $exception = new class('Test error message') extends TicketMailerException {
            public function getUserMessage(): string
            {
                return 'User friendly error message';
            }

            public function getDebugInfo(): array
            {
                return [
                    'exception' => static::class,
                    'message' => $this->getMessage(),
                    'code' => $this->getCode(),
                    'file' => $this->getFile(),
                    'line' => $this->getLine(),
                    'context' => []
                ];
            }
        };

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'TicketMailer Exception occurred',
                $this->callback(function($context) {
                    return isset($context['context']) && 
                           isset($context['exception']) && 
                           isset($context['trace']);
                })
            );

        $this->flashBag->expects($this->once())
            ->method('add')
            ->with('error', 'User friendly error message');

        $this->errorHandlingService->handleTicketMailerException($exception, 'test context');
    }

    public function testHandleTicketMailerExceptionWithEmptyContext(): void
    {
        $exception = new class('Test error') extends TicketMailerException {
            public function getUserMessage(): string
            {
                return 'Error message';
            }

            public function getDebugInfo(): array
            {
                return ['message' => $this->getMessage()];
            }
        };

        $this->logger->expects($this->once())
            ->method('error')
            ->with('TicketMailer Exception occurred', $this->arrayHasKey('context'));

        $this->flashBag->expects($this->once())
            ->method('add')
            ->with('error', 'Error message');

        $this->errorHandlingService->handleTicketMailerException($exception);
    }

    public function testHandleGeneralExceptionLogsErrorAndAddsFlashMessage(): void
    {
        $exception = new \RuntimeException('Runtime error occurred', 500);
        $context = 'Testing context';
        $userMessage = 'Something went wrong';

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected exception occurred',
                $this->callback(function($logContext) use ($context, $exception) {
                    return $logContext['context'] === $context &&
                           $logContext['exception'] === $exception->getMessage() &&
                           isset($logContext['file']) &&
                           isset($logContext['line']) &&
                           isset($logContext['trace']);
                })
            );

        $this->flashBag->expects($this->once())
            ->method('add')
            ->with('error', $userMessage);

        $this->errorHandlingService->handleGeneralException($exception, $context, $userMessage);
    }

    public function testHandleGeneralExceptionWithDefaultMessage(): void
    {
        $exception = new \Exception('Test exception');

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Unexpected exception occurred', $this->isType('array'));

        $this->flashBag->expects($this->once())
            ->method('add')
            ->with('error', 'Ein unerwarteter Fehler ist aufgetreten.');

        $this->errorHandlingService->handleGeneralException($exception);
    }

    public function testLogWarningWithUserMessage(): void
    {
        $message = 'Warning message';
        $context = ['key' => 'value'];
        $userMessage = 'User warning';

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($message, $context);

        $this->flashBag->expects($this->once())
            ->method('add')
            ->with('warning', $userMessage);

        $this->errorHandlingService->logWarning($message, $context, $userMessage);
    }

    public function testLogWarningWithoutUserMessage(): void
    {
        $message = 'Warning message';
        $context = ['key' => 'value'];

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($message, $context);

        $this->flashBag->expects($this->never())
            ->method('add');

        $this->errorHandlingService->logWarning($message, $context);
    }

    public function testLogWarningWithNullUserMessage(): void
    {
        $message = 'Warning message';

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($message, []);

        $this->flashBag->expects($this->never())
            ->method('add');

        $this->errorHandlingService->logWarning($message, [], null);
    }

    public function testLogInfoWithUserMessage(): void
    {
        $message = 'Info message';
        $context = ['data' => 'test'];
        $userMessage = 'User info';

        $this->logger->expects($this->once())
            ->method('info')
            ->with($message, $context);

        $this->flashBag->expects($this->once())
            ->method('add')
            ->with('info', $userMessage);

        $this->errorHandlingService->logInfo($message, $context, $userMessage);
    }

    public function testLogInfoWithoutUserMessage(): void
    {
        $message = 'Info message';

        $this->logger->expects($this->once())
            ->method('info')
            ->with($message, []);

        $this->flashBag->expects($this->never())
            ->method('add');

        $this->errorHandlingService->logInfo($message);
    }

    public function testLogSuccess(): void
    {
        $message = 'Success message';
        $context = ['result' => 'ok'];

        $this->logger->expects($this->once())
            ->method('info')
            ->with($message, $context);

        $this->flashBag->expects($this->once())
            ->method('add')
            ->with('success', $message);

        $this->errorHandlingService->logSuccess($message, $context);
    }

    public function testLogSuccessWithEmptyContext(): void
    {
        $message = 'Success message';

        $this->logger->expects($this->once())
            ->method('info')
            ->with($message, []);

        $this->flashBag->expects($this->once())
            ->method('add')
            ->with('success', $message);

        $this->errorHandlingService->logSuccess($message);
    }

    public function testIsCriticalExceptionReturnsTrueForDoctrineException(): void
    {
        $exception = $this->createMock(DoctrineException::class);

        $result = $this->errorHandlingService->isCriticalException($exception);

        $this->assertTrue($result);
    }

    public function testIsCriticalExceptionReturnsTrueForAuthenticationException(): void
    {
        $exception = $this->createMock(AuthenticationException::class);

        $result = $this->errorHandlingService->isCriticalException($exception);

        $this->assertTrue($result);
    }

    public function testIsCriticalExceptionReturnsTrueForTransportException(): void
    {
        $exception = $this->createMock(TransportException::class);

        $result = $this->errorHandlingService->isCriticalException($exception);

        $this->assertTrue($result);
    }

    public function testIsCriticalExceptionReturnsFalseForStandardException(): void
    {
        $exception = new \RuntimeException('Standard runtime exception');

        $result = $this->errorHandlingService->isCriticalException($exception);

        $this->assertFalse($result);
    }

    public function testIsCriticalExceptionReturnsFalseForInvalidArgumentException(): void
    {
        $exception = new \InvalidArgumentException('Invalid argument');

        $result = $this->errorHandlingService->isCriticalException($exception);

        $this->assertFalse($result);
    }

    public function testIsCriticalExceptionReturnsFalseForLogicException(): void
    {
        $exception = new \LogicException('Logic error');

        $result = $this->errorHandlingService->isCriticalException($exception);

        $this->assertFalse($result);
    }

    public function testConstructorInitializesServices(): void
    {
        $service = new ErrorHandlingService($this->logger, $this->requestStack);
        
        $this->assertInstanceOf(ErrorHandlingService::class, $service);
    }

    public function testHandleGeneralExceptionLogsAllExceptionDetails(): void
    {
        $exception = new \Exception('Detailed test exception', 123);
        
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected exception occurred',
                $this->callback(function($context) use ($exception) {
                    return $context['exception'] === $exception->getMessage() &&
                           $context['file'] === $exception->getFile() &&
                           $context['line'] === $exception->getLine() &&
                           is_string($context['trace']);
                })
            );

        $this->flashBag->expects($this->once())
            ->method('add')
            ->with('error', 'Ein unerwarteter Fehler ist aufgetreten.');

        $this->errorHandlingService->handleGeneralException($exception);
    }
}