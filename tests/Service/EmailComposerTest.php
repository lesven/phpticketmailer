<?php

namespace App\Tests\Service;

use App\Dto\TemplateResolutionResult;
use App\Entity\User;
use App\Service\EmailComposer;
use App\Service\TemplateService;
use App\ValueObject\EmailAddress;
use App\ValueObject\TicketData;
use PHPUnit\Framework\TestCase;

class EmailComposerTest extends TestCase
{
    private TemplateService $templateService;
    private EmailComposer $composer;

    protected function setUp(): void
    {
        $this->templateService = $this->createMock(TemplateService::class);

        $this->templateService->method('resolveTemplateForTicketDate')
            ->willReturn(new TemplateResolutionResult(
                content: '<p>Template {{username}} {{ticketId}}</p>',
                inputCreated: null,
                parsedDate: null,
                selectedTemplateName: 'default',
                selectedTemplateValidFrom: null,
                selectionMethod: 'mock_default',
                allTemplates: [],
            ));

        $this->templateService->method('getDefaultContent')
            ->willReturn('<p>Template {{username}} {{ticketId}}</p>');

        $this->templateService->method('replacePlaceholders')
            ->willReturnCallback(function (string $template, array $variables): string {
                $dueDate = new \DateTime('+7 days');
                $germanMonths = [
                    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
                    5 => 'Mai',    6 => 'Juni',     7 => 'Juli',  8 => 'August',
                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
                ];
                $defaults = [
                    'ticketId'   => 'TICKET-ID',
                    'ticketName' => 'Ticket-Name',
                    'username'   => 'Benutzername',
                    'ticketLink' => 'https://www.ticket.de/ticket-id',
                    'dueDate'    => $dueDate->format('d') . '. ' . $germanMonths[(int) $dueDate->format('n')] . ' ' . $dueDate->format('Y'),
                    'created'    => '',
                ];
                $merged = array_merge($defaults, $variables);
                foreach ($merged as $key => $value) {
                    $template = str_replace('{{' . $key . '}}', (string) $value, $template);
                }
                return $template;
            });

        $this->composer = new EmailComposer($this->templateService);
    }

    public function testPrepareEmailContentReplacesPlaceholdersAndAddsTestPrefix(): void
    {
        $template = "Hallo {{username}},\nTicket: {{ticketId}}\nLink: {{ticketLink}}\nFällig: {{dueDate}}\n{{ticketName}}";
        $ticketData = TicketData::fromStrings('T-123', 'jsmith', 'Problem');

        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('jsmith@example.com'));

        $result = $this->composer->prepareEmailContent($template, $ticketData, $user, 'https://tickets.example', true);

        $this->assertStringContainsString('*** TESTMODUS - E-Mail wäre an jsmith@example.com gegangen ***', $result);
        $this->assertStringNotContainsString('{{ticketId}}', $result);
        $this->assertStringContainsString('T-123', $result);
        $this->assertStringContainsString('https://tickets.example/T-123', $result);
        $this->assertStringContainsString('Problem', $result);
        $this->assertMatchesRegularExpression('/\d{1,2}\.\s+\p{L}+\s+\d{4}/u', $result);
    }

    public function testPrepareEmailContentNonTestModeDoesNotPrefix(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('u@example.com'));
        $template = "Hello {{username}} - {{ticketId}} - {{ticketLink}} - {{dueDate}}";

        $out = $this->composer->prepareEmailContent(
            $template,
            TicketData::fromStrings('ZZZ-001', 'bob', 'Test Ticket'),
            $user,
            'https://base',
            false,
        );

        $this->assertStringNotContainsString('*** TESTMODUS', $out);
        $this->assertStringContainsString('ZZZ-001', $out);
    }

    public function testPrepareEmailContentWithNullUserDoesNotAddPrefix(): void
    {
        $template = "Hello {{username}} - {{ticketId}}";
        $ticketData = TicketData::fromStrings('T-001', 'nouser', 'Test');

        $result = $this->composer->prepareEmailContent($template, $ticketData, null, 'https://base', true);

        $this->assertStringNotContainsString('*** TESTMODUS', $result);
        $this->assertStringContainsString('T-001', $result);
    }

    public function testResolveTemplateContentReturnsTemplateContent(): void
    {
        $ticketData = TicketData::fromStrings('T-100', 'user', 'Test');

        $content = $this->composer->resolveTemplateContent($ticketData, 'fallback content');

        $this->assertStringContainsString('Template', $content);
    }

    public function testResolveTemplateContentFallsBackToGlobalWhenEmpty(): void
    {
        $this->templateService = $this->createMock(TemplateService::class);
        $this->templateService->method('resolveTemplateForTicketDate')
            ->willReturn(new TemplateResolutionResult(
                content: '',
                selectionMethod: 'empty_test',
            ));
        $this->templateService->method('getDefaultContent')
            ->willReturn('fallback');

        $composer = new EmailComposer($this->templateService);
        $ticketData = TicketData::fromStrings('T-200', 'user', 'Test');

        $content = $composer->resolveTemplateContent($ticketData, 'global fallback');

        $this->assertEquals('global fallback', $content);
    }

    public function testGetTemplateDebugInfoIsPopulatedAfterResolve(): void
    {
        $ticketData = TicketData::fromStrings('T-300', 'user', 'Test');

        $this->composer->resolveTemplateContent($ticketData, 'fallback');

        $debugInfo = $this->composer->getTemplateDebugInfo();
        $this->assertArrayHasKey('T-300', $debugInfo);
        $this->assertEquals('mock_default', $debugInfo['T-300']['selectionMethod']);
    }

    public function testGetDefaultContentDelegatesToTemplateService(): void
    {
        $this->assertEquals('<p>Template {{username}} {{ticketId}}</p>', $this->composer->getDefaultContent());
    }

    public function testResetTemplateDebugInfoClearsData(): void
    {
        $ticketData = TicketData::fromStrings('T-400', 'user', 'Test');
        $this->composer->resolveTemplateContent($ticketData, 'fallback');

        $this->assertNotEmpty($this->composer->getTemplateDebugInfo());

        $this->composer->resetTemplateDebugInfo();

        $this->assertEmpty($this->composer->getTemplateDebugInfo());
    }
}
